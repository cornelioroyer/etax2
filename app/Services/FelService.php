<?php

namespace App\Services;

use App\Models\FelConfiguracion;
use SoapClient;
use Throwable;

/**
 * Cliente del web service de The Factory HKA (PAC Panamá) para
 * facturación electrónica (FEL) ante la DGI.
 *
 * Wiki: https://felwiki.thefactoryhka.com.pa
 */
class FelService
{
    private const WSDL = [
        'PRUEBAS' => 'https://demoemision.thefactoryhka.com.pa/ws/obj/v1.0/Service.svc?singleWsdl',
        'PRODUCCION' => 'https://emision.thefactoryhka.com.pa/ws/obj/v1.0/Service.svc?singleWsdl',
    ];

    private ?SoapClient $client = null;

    public function __construct(private readonly FelConfiguracion $config)
    {
    }

    /**
     * Envía un documento electrónico (factura, nota de crédito, etc.).
     * Devuelve la respuesta normalizada como array.
     */
    public function enviar(array $documento): array
    {
        return $this->llamar('Enviar', ['documento' => $documento]);
    }

    /** Folios disponibles en el PAC (sirve también como prueba de conexión). */
    public function foliosRestantes(): array
    {
        return $this->llamar('FoliosRestantes');
    }

    /** Consulta el estado de un documento ya enviado. */
    public function estadoDocumento(array $datosDocumento): array
    {
        return $this->llamar('EstadoDocumento', $datosDocumento);
    }

    /** Anula un documento autorizado. */
    public function anulacionDocumento(array $datosDocumento, string $motivo = ''): array
    {
        $extra = $datosDocumento;
        if ($motivo !== '') {
            $extra['motivoAnulacion'] = $motivo;
        }

        return $this->llamar('AnulacionDocumento', $extra);
    }

    /** Descarga el CAFE en PDF (base64 en la respuesta). */
    public function descargaPDF(array $datosDocumento): array
    {
        return $this->llamar('DescargaPDF', $datosDocumento);
    }

    /** Descarga el XML firmado del documento. */
    public function descargaXML(array $datosDocumento): array
    {
        return $this->llamar('DescargaXML', $datosDocumento);
    }

    /**
     * Llama un método del WS con los tokens de la compañía y
     * normaliza la respuesta SOAP a un array asociativo.
     */
    private function llamar(string $metodo, array $extra = []): array
    {
        $parametros = array_merge([
            'tokenEmpresa' => $this->config->token_empresa,
            'tokenPassword' => $this->config->token_password,
        ], $extra);

        try {
            $respuesta = $this->cliente()->__soapCall($metodo, [$parametros]);

            return json_decode(json_encode($respuesta), true) ?: [];
        } catch (Throwable $e) {
            return [
                'error' => true,
                'codigo' => 'SOAP',
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    private function cliente(): SoapClient
    {
        if ($this->client === null) {
            $this->client = new SoapClient(self::WSDL[$this->config->ambiente] ?? self::WSDL['PRUEBAS'], [
                'exceptions' => true,
                'connection_timeout' => 30,
                'cache_wsdl' => WSDL_CACHE_DISK,
                'stream_context' => stream_context_create(['http' => ['timeout' => 60]]),
            ]);
        }

        return $this->client;
    }
}
