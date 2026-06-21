<?php

namespace App\Services;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta la factura electrónica pública de la DGI (Panamá) por su CUFE y
 * extrae el encabezado, el emisor y el detalle de líneas.
 *
 * Fuente: https://dgi-fep.mef.gob.pa/Consultas/FacturasPorCUFE/{CUFE}
 * Es una página HTML renderizada en servidor (sin captcha); el CUFE va en la
 * ruta. Se raspa el DOM porque el XML firmado solo lo expone el PAC al emisor,
 * no al receptor de la compra.
 */
class DgiFepConsulta
{
    private const BASE = 'https://dgi-fep.mef.gob.pa/Consultas/FacturasPorCUFE/';

    /**
     * Devuelve la factura parseada, o null si no se pudo consultar/parsear.
     *
     * @return array{cufe:string,numero:?string,tipo:string,fecha:?string,emisor:array{ruc:?string,dv:?string,nombre:?string,direccion:?string,telefono:?string},receptor:array{ruc:?string,dv:?string,nombre:?string,direccion:?string,telefono:?string},subtotal:float,itbms:float,total:float,lineas:array<int,array{codigo:string,descripcion:string,cantidad:float,precio_unitario:float,descuento:float,monto:float,itbms:float,total:float}>}|null
     */
    public function porCufe(string $cufe): ?array
    {
        $cufe = trim($cufe);
        if ($cufe === '') {
            return null;
        }

        try {
            $resp = Http::timeout(25)
                ->retry(1, 1500)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (etax2 FEL import)'])
                ->get(self::BASE.rawurlencode($cufe));

            if (! $resp->successful()) {
                return null;
            }

            return $this->parsear($cufe, $resp->body());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Consulta a partir del contenido del QR de la factura (o de un CUFE pelado).
     *
     * El QR codifica una URL con chFE (el CUFE de 66 caracteres). Basta extraer
     * ese CUFE y pedir FacturasPorCUFE/{cufe}: ese GET devuelve el detalle directo,
     * sin reCAPTCHA (el captcha solo protege el buscador manual). La clave es usar
     * el CUFE COMPLETO de 66 caracteres — uno truncado devuelve el formulario vacío.
     *
     * @return array{ok:bool,motivo?:string,mensaje?:string,factura?:array,cufe?:?string}
     */
    public function porQr(string $qr): array
    {
        $qr   = trim($qr);
        $cufe = $this->extraerCufe($qr);

        if (! $cufe) {
            $muestra = mb_strlen($qr) > 140 ? mb_substr($qr, 0, 140).'…' : $qr;
            return [
                'ok'       => false,
                'motivo'   => 'sin_cufe',
                'mensaje'  => 'No se encontró un CUFE en el QR. Contenido leído: '.$muestra,
                'recibido' => $muestra,
            ];
        }

        if (strlen($cufe) !== 66) {
            return [
                'ok'      => false,
                'motivo'  => 'cufe_invalido',
                'mensaje' => 'El CUFE leído tiene '.strlen($cufe).' caracteres (deben ser 66). Reescanea el QR bien centrado.',
                'cufe'    => $cufe,
            ];
        }

        $factura = $this->porCufe($cufe);
        if (! $factura) {
            return ['ok' => false, 'motivo' => 'no_encontrada', 'mensaje' => 'La DGI no devolvió la factura para ese CUFE.', 'cufe' => $cufe];
        }

        return ['ok' => true, 'factura' => $factura, 'cufe' => $cufe];
    }

    /**
     * Descarga el PDF oficial de la factura desde la DGI.
     *
     * La página FacturasPorCUFE/{cufe} trae un campo oculto `facturaXML` (blob
     * cifrado); al hacer POST de ese campo a DescargarFacturaPDF la DGI devuelve
     * el PDF. Devuelve el binario del PDF, o null si no se pudo.
     */
    public function pdfPorCufe(string $cufe): ?string
    {
        $cufe = trim($cufe);
        if ($cufe === '') {
            return null;
        }

        try {
            $jar = new \GuzzleHttp\Cookie\CookieJar;
            $ua  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

            $pagina = Http::withOptions(['cookies' => $jar])
                ->timeout(25)
                ->withHeaders(['User-Agent' => $ua])
                ->get(self::BASE.rawurlencode($cufe));

            if (! $pagina->successful()
                || ! preg_match('/name="facturaXML"[^>]*value="([^"]*)"/i', $pagina->body(), $m)) {
                return null;
            }

            $facturaXML = html_entity_decode($m[1]);

            $pdf = Http::withOptions(['cookies' => $jar])
                ->timeout(40)
                ->asForm()
                ->withHeaders([
                    'User-Agent' => $ua,
                    'Referer'    => self::BASE.rawurlencode($cufe),
                ])
                ->post('https://dgi-fep.mef.gob.pa/Consultas/DescargarFacturaPDF', [
                    'facturaXML' => $facturaXML,
                ]);

            $cuerpo = $pdf->body();

            return ($pdf->successful() && str_starts_with($cuerpo, '%PDF')) ? $cuerpo : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Extrae el CUFE de una URL de QR (chFE=), de una ruta /FacturasPorCUFE/, o del valor pelado. */
    private function extraerCufe(string $s): ?string
    {
        $s = trim($s);
        if (preg_match('/[?&]chFE=([^&\s]+)/i', $s, $m)) {
            return rawurldecode($m[1]);
        }
        if (preg_match('#/FacturasPorCUFE/([A-Za-z0-9\-]+)#i', $s, $m)) {
            return $m[1];
        }
        if (preg_match('/^(FE\d{2}[0-9A-Za-z\-]{20,})$/i', $s, $m)) {
            return $m[1];
        }
        return null;
    }

    private function parsear(string $cufe, string $html, bool $exigirCufe = true): ?array
    {
        if (stripos($html, 'detalle') === false || ($exigirCufe && stripos($html, $cufe) === false)) {
            return null; // CUFE no encontrado o página inesperada
        }

        $previo = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $xp = new DOMXPath($doc);

        $lineas = $this->lineas($xp);
        if ($lineas === []) {
            return null; // sin detalle utilizable
        }

        $emisor   = $this->emisor($xp);
        $receptor = $this->receptor($xp);

        $subtotalLineas = round(array_sum(array_column($lineas, 'monto')), 2);
        $itbmsLineas = round(array_sum(array_column($lineas, 'itbms')), 2);

        $valorTotal = $this->montoPie($xp, 'Valor Total');
        $itbmsTotal = $this->montoPie($xp, 'ITBMS Total');

        $total = $valorTotal ?? round($subtotalLineas + $itbmsLineas, 2);
        $itbms = $itbmsTotal ?? $itbmsLineas;
        $subtotal = round($total - $itbms, 2);

        return [
            'cufe'     => $cufe,
            'numero'   => $this->numeroDocumento($xp),
            'tipo'     => $this->tipoDocumento($xp),
            'fecha'    => $this->fechaDocumento($xp),
            'emisor'   => $emisor,
            'receptor' => $receptor,
            'subtotal' => $subtotal,
            'itbms'    => $itbms,
            'total'    => $total,
            'lineas'   => $lineas,
        ];
    }

    /** @return array<int,array<string,float|string>> */
    private function lineas(DOMXPath $xp): array
    {
        $filas = $xp->query("//*[@id='detalle']//tbody/tr");
        if ($filas === false) {
            return [];
        }

        $lineas = [];
        foreach ($filas as $fila) {
            $celda = function (string $title) use ($xp, $fila): string {
                $nodos = $xp->query(".//td[@data-title='{$title}']", $fila);

                return $nodos && $nodos->length ? trim($nodos->item(0)->textContent) : '';
            };

            $descripcion = $celda('Descripción');
            $monto = $this->num($celda('Monto'));
            $totalLinea = $this->num($celda('Total'));

            // Una fila sin descripción ni monto no es una línea real.
            if ($descripcion === '' && $monto == 0.0 && $totalLinea == 0.0) {
                continue;
            }

            $lineas[] = [
                // Código del artículo según lo registró el emisor (puede venir vacío).
                'codigo'          => $celda('Código'),
                'descripcion'     => $descripcion !== '' ? $descripcion : 'Sin descripción',
                'cantidad'        => $this->num($celda('Cantidad')) ?: 1.0,
                'precio_unitario' => $this->num($celda('Precio')),
                'descuento'       => $this->num($celda('Descuento')),
                'monto'           => $monto,
                'itbms'           => $this->num($celda('Impuesto')),
                'total'           => $totalLinea ?: $monto,
            ];
        }

        return $lineas;
    }

    /** @return array{ruc:?string,dv:?string,nombre:?string,direccion:?string,telefono:?string} */
    private function emisor(DOMXPath $xp): array
    {
        $panel = $this->buscarPanel($xp, 'EMISOR');

        $data = [
            'ruc'       => $this->ddPorDt($xp, 'RUC', $panel),
            'dv'        => $this->ddPorDt($xp, 'DV', $panel),
            'nombre'    => $this->ddPorDt($xp, 'NOMBRE', $panel),
            'direccion' => $this->ddPorDt($xp, 'DIRECCI', $panel),
            'telefono'  => $this->ddPorDt($xp, 'FONO', $panel),
        ];

        Log::debug('DGI-FEP emisor', ['panel_encontrado' => $panel !== null, 'ruc' => $data['ruc'], 'nombre' => $data['nombre']]);

        return $data;
    }

    /** @return array{ruc:?string,dv:?string,nombre:?string,direccion:?string,telefono:?string} */
    private function receptor(DOMXPath $xp): array
    {
        $panel = $this->buscarPanel($xp, 'RECEPTOR');

        return [
            'ruc'       => $this->ddPorDt($xp, 'RUC', $panel),
            'dv'        => $this->ddPorDt($xp, 'DV', $panel),
            'nombre'    => $this->ddPorDt($xp, 'NOMBRE', $panel),
            'direccion' => $this->ddPorDt($xp, 'DIRECCI', $panel),
            'telefono'  => $this->ddPorDt($xp, 'FONO', $panel),
        ];
    }

    /**
     * Localiza el panel del EMISOR o RECEPTOR tolerando variaciones del HTML de la DGI:
     * mayúsculas/minúsculas, "Datos del Emisor", elementos distintos a panel-heading, etc.
     */
    private function buscarPanel(DOMXPath $xp, string $rol): ?DOMNode
    {
        // XPath case-insensitive vía translate() — solo las letras que varían entre ROL y minúsculas
        $upper  = strtoupper($rol);
        $lower  = strtolower($rol);
        $chars  = implode('', array_unique(str_split($lower)));
        $CHARS  = implode('', array_unique(str_split($upper)));
        $trans  = "translate(normalize-space(.),'$chars','$CHARS')";

        // 1) Selector original: panel > panel-heading exacto
        $q = $xp->query("//div[contains(@class,'panel')][.//div[contains(@class,'panel-heading')][contains($trans,'$upper')]]");
        if ($q && $q->length) {
            return $q->item(0);
        }

        // 2) Cualquier elemento hijo del panel con el texto del rol
        $q = $xp->query("//div[contains(@class,'panel')][.//*[contains($trans,'$upper')]]");
        if ($q && $q->length) {
            return $q->item(0);
        }

        // 3) Buscar sección por encabezado h3/h4/h5 con el texto, y tomar el contenedor más cercano
        $q = $xp->query("//*[self::h3 or self::h4 or self::h5 or self::b or self::strong][contains($trans,'$upper')]/ancestor::div[1]");
        if ($q && $q->length) {
            return $q->item(0);
        }

        Log::warning("DGI-FEP: no se encontró panel $rol en el HTML de la DGI.");

        return null;
    }

    /** Valor del <dd> cuyo <dt> hermano contiene el texto dado, dentro de $ctx. */
    private function ddPorDt(DOMXPath $xp, string $dt, ?DOMNode $ctx): ?string
    {
        $expr = ".//dt[contains(normalize-space(.),'{$dt}')]/following-sibling::dd[1]";
        $nodos = $ctx ? $xp->query($expr, $ctx) : $xp->query('/'.$expr);

        return $nodos && $nodos->length ? (trim($nodos->item(0)->textContent) ?: null) : null;
    }

    private function numeroDocumento(DOMXPath $xp): ?string
    {
        $nodos = $xp->query("//h5[contains(.,'No.')]");
        if ($nodos && $nodos->length && preg_match('/No\.\s*([0-9A-Za-z\-]+)/', $nodos->item(0)->textContent, $m)) {
            return ltrim($m[1], '0') !== '' ? ltrim($m[1], '0') : $m[1];
        }

        return null;
    }

    private function tipoDocumento(DOMXPath $xp): string
    {
        $nodos = $xp->query('//h4/strong');
        $txt = $nodos && $nodos->length ? mb_strtoupper(trim($nodos->item(0)->textContent)) : '';

        if (str_contains($txt, 'CRÉDITO') || str_contains($txt, 'CREDITO')) {
            return 'NOTA_CREDITO';
        }
        if (str_contains($txt, 'DÉBITO') || str_contains($txt, 'DEBITO')) {
            return 'NOTA_DEBITO';
        }

        return 'FACTURA';
    }

    private function fechaDocumento(DOMXPath $xp): ?string
    {
        $nodos = $xp->query("//h5[contains(.,'/')]");
        foreach ($nodos ?: [] as $n) {
            if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $n->textContent, $m)) {
                return "{$m[3]}-{$m[2]}-{$m[1]}";
            }
        }

        return null;
    }

    /** Lee un monto del pie de la tabla (ej. "Valor Total: 111.24"). */
    private function montoPie(DOMXPath $xp, string $etiqueta): ?float
    {
        $celdas = $xp->query("//*[@id='detalle']//tfoot//td");
        foreach ($celdas ?: [] as $td) {
            $texto = preg_replace('/\s+/', ' ', trim($td->textContent));
            if (stripos($texto, $etiqueta) !== false && preg_match('/-?[\d,]+\.\d{2}/', $texto, $m)) {
                return $this->num($m[0]);
            }
        }

        return null;
    }

    private function num(string $valor): float
    {
        $limpio = str_replace([',', 'B/.', ' '], '', $valor);

        return is_numeric($limpio) ? round((float) $limpio, 2) : 0.0;
    }
}
