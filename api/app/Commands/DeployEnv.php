<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Inicializa el archivo .env a partir de la plantilla base y genera las claves secretas.
 *
 * Uso:
 *   php spark deploy:env          Crea .env si no existe y genera secretos vacíos o comentados
 *   php spark deploy:env --force  Fuerza la regeneración de todos los secretos existentes
 */
class DeployEnv extends BaseCommand
{
    protected $group       = 'APP';
    protected $name        = 'deploy:env';
    protected $description = 'Inicializa el archivo .env desde la plantilla y genera secretos criptográficos.';
    protected $usage       = 'deploy:env [options]';
    protected $options     = [
        '--force' => 'Fuerza la regeneración de las claves secretas existentes.',
    ];

    public function run(array $params)
    {
        $envPath  = ROOTPATH . '.env';
        $template = ROOTPATH . 'env';
        $force    = array_key_exists('force', $params)
            || in_array('--force', $params, true)
            || in_array('force', $params, true)
            || CLI::getOption('force') !== null;

        if (!file_exists($envPath)) {
            if (!file_exists($template)) {
                CLI::error("No se encontró la plantilla 'env' en: {$template}");
                return;
            }
            if (!copy($template, $envPath)) {
                CLI::error('No se pudo copiar la plantilla env a .env.');
                return;
            }
            CLI::write('✓ Archivo .env creado a partir de la plantilla base.', 'green');
        }

        $content = (string) file_get_contents($envPath);
        $updated = 0;

        $secrets = [
            'encryption.key'      => 'hex2bin:' . bin2hex(random_bytes(32)),
            'jwt.authSecretKey'   => bin2hex(random_bytes(32)),
            'jwt.actionSecretKey' => bin2hex(random_bytes(32)),
        ];

        foreach ($secrets as $key => $val) {
            $pattern = '/^[#\s]*' . preg_quote($key, '/') . '\s*=.*$/m';
            if (preg_match($pattern, $content, $match)) {
                $isCommented = str_starts_with(trim($match[0]), '#');
                $currentVal  = trim(explode('=', $match[0], 2)[1] ?? '');

                if ($force || $isCommented || $currentVal === '') {
                    $content = preg_replace($pattern, "{$key} = {$val}", $content, 1);
                    CLI::write("  [+] Secreto generado: {$key}", 'yellow');
                    $updated++;
                } else {
                    CLI::write("  [=] Secreto conservado: {$key} (use --force para rotar)");
                }
            } else {
                $content = rtrim($content) . "\n{$key} = {$val}\n";
                CLI::write("  [+] Secreto añadido: {$key}", 'yellow');
                $updated++;
            }
        }

        if ($updated > 0) {
            $tmp = $envPath . '.tmp.' . uniqid();
            if (file_put_contents($tmp, $content) === false || !@rename($tmp, $envPath)) {
                @unlink($tmp);
                CLI::error('Error al guardar los cambios en .env.');
                return;
            }
            CLI::write("✓ Se actualizaron {$updated} secreto(s) en .env con éxito.", 'green');
        } else {
            CLI::write('✓ No se requirieron cambios en .env. Todos los secretos están presentes.', 'green');
        }
    }
}
