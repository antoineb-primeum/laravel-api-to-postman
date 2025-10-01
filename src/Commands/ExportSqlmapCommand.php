<?php

namespace AndreasElia\PostmanGenerator\Commands;

use AndreasElia\PostmanGenerator\SqlMap\Exporter as SqlMapExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ExportSqlmapCommand extends Command
{
    /** @var string */
    protected $signature = 'export:sqlmap
                            {--bearer= : The bearer token to use on your endpoints}
                            {--basic= : The basic auth to use on your endpoints}';

    /** @var string */
    protected $description = 'Automatically generate a SqlMap file for your API routes';

    public function handle(SqlMapExporter $exporter): void
    {
        $filename = str_replace(
            ['{timestamp}', '{app}'],
            [date('Y_m_d_His'), Str::snake(config('app.name'))],
            config('api-exports.filename')
        );

        config()->set('api-exports.authentication', [
            'method' => $this->option('bearer') ? 'bearer' : ($this->option('basic') ? 'basic' : null),
            'token' => $this->option('bearer') ?? $this->option('basic') ?? null,
        ]);

        $exporter
            ->to($filename)
            ->setAuthentication(value(function () {
                if (filled($this->option('bearer'))) {
                    return new \AndreasElia\PostmanGenerator\Authentication\Bearer($this->option('bearer'));
                }
                if (filled($this->option('basic'))) {
                    return new \AndreasElia\PostmanGenerator\Authentication\Basic($this->option('basic'));
                }
                return null;
            }))->export();

        // Optionnel : log debug de la structure
        // $structure = $exporter->generateStructure();
        // file_put_contents(base_path('sqlmap_command.log'), var_export($structure, true));
        $structure = $exporter->generateStructure();
        $items = [];
        $exporter->traverseItems($structure['item'] ?? [], $items);
        $host = parse_url(config('api-exports.base_url'), PHP_URL_HOST) ?? 'localhost';
        $timestamp = date('Y_m_d_His');
        foreach ($items as $item) {
            $req = $item['request'];
            $method = strtoupper($req['method'] ?? 'GET');
            $url = $req['url']['raw'] ?? $req['url'] ?? '/';
            $url = preg_replace('/\{[A-Za-z0-9_]+\}/', 'FUZZ', $url);
            $url = preg_replace('/\:[A-Za-z0-9_]+/', 'FUZZ', $url);
            $template = $method.' '.$url.' HTTP/1.1' . "\n";
            $template .= 'Host: '.$host."\n";
            foreach ($req['header'] ?? [] as $header) {
                if (strtolower($header['key']) !== 'host') {
                    $template .= $header['key'].': '.$header['value']."\n";
                }
            }
            $body = '';
            if (in_array($method, ['POST','PUT','PATCH','DELETE'])) {
                if (isset($req['body']['urlencoded']) && is_array($req['body']['urlencoded'])) {
                    $pairs = [];
                    foreach ($req['body']['urlencoded'] as $field) {
                        $pairs[] = $field['key'].'=FUZZ';
                    }
                    $body = implode('&', $pairs);
                } elseif (isset($req['body']['raw']) && $req['body']['raw'] !== '') {
                    $body = str_replace(['\n','\r'], '', $req['body']['raw']);
                }
                if ($body !== '') {
                    $template .= 'Content-Length: '.strlen($body)."\n\n";
                    $template .= $body;
                }
            }
            $filename = $timestamp.'_'.str_replace(['/','{','}','\\',':'], '_', $item['name']).'_'.$method.'.template';
            Storage::disk('local')->put('private/sqlmap/'.$filename, $template);
        }
        $this->info('Export SQLMap terminé. Les fichiers .template sont disponibles dans le disque local/private/sqlmap/');
    }
}
