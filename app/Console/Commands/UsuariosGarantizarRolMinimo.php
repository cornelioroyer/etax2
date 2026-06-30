<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Backfill del invariante "rol mínimo": ningún usuario puede quedar sin ningún
 * rol. Recorre todos los usuarios y, a los que no tengan rol alguno (ni por
 * compañía en seg_usuarios_roles ni global en seg_usuarios_roles_globales), les
 * reasigna el rol base "usuario" en la compañía por defecto (1).
 *
 * Idempotente: no toca a quienes ya tienen al menos un rol (respeta roles
 * restrictivos). Reversible identificando las filas creadas en seg_usuarios_roles
 * con rol "usuario" en la compañía por defecto para los usuarios listados.
 */
class UsuariosGarantizarRolMinimo extends Command
{
    protected $signature = 'usuarios:garantizar-rol-minimo {--compania=1 : Compañía por defecto donde reinstaurar el rol base} {--dry-run : Solo listar, no escribir}';

    protected $description = 'Reasigna el rol base «usuario» a los usuarios que hayan quedado sin ningún rol (idempotente).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $companiaDefault = (int) $this->option('compania');

        $reinstaurados = 0;
        $intactos = 0;

        User::query()->orderBy('id')->chunkById(200, function ($usuarios) use ($dry, $companiaDefault, &$reinstaurados, &$intactos) {
            foreach ($usuarios as $user) {
                $sinRoles = ! \Illuminate\Support\Facades\DB::table('seg_usuarios_roles')
                    ->where('model_type', User::class)
                    ->where('model_id', $user->id)
                    ->exists()
                    && ! $user->tieneAsignacionGlobal();

                if (! $sinRoles) {
                    $intactos++;

                    continue;
                }

                if ($dry) {
                    $this->line("  [dry-run] {$user->email} (id {$user->id}) quedaría con rol «usuario» en compañía {$companiaDefault}");
                    $reinstaurados++;

                    continue;
                }

                if ($user->garantizarRolMinimo($companiaDefault)) {
                    $this->line("  ✔ {$user->email} (id {$user->id}) → rol «usuario» en compañía {$companiaDefault}");
                    $reinstaurados++;
                } else {
                    // No se pudo (falta rol o compañía): reportar para revisión manual.
                    $this->warn("  ! {$user->email} (id {$user->id}) sin roles pero NO se pudo reinstaurar (¿existe rol «usuario» / compañía {$companiaDefault}?)");
                }
            }
        });

        $this->info(($dry ? '[dry-run] ' : '')."Reinstaurados: {$reinstaurados} · Ya tenían rol: {$intactos}");

        return self::SUCCESS;
    }
}
