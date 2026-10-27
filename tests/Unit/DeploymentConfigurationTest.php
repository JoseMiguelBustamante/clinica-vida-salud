<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeploymentConfigurationTest extends TestCase
{
    private function source(string $path): string
    {
        $file = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($file);

        return file_get_contents($file);
    }

    public function test_image_installs_production_dependencies_without_provisioning(): void
    {
        $docker = $this->source('Dockerfile');
        $this->assertStringContainsString('FROM composer:2 AS composer', $docker);
        $this->assertStringContainsString('FROM php:8.3-apache-bookworm', $docker);
        foreach (['libpq-dev', 'libonig-dev', 'libxml2-dev', 'libicu-dev', 'docker-php-ext-install', 'pdo_pgsql', 'mbstring', 'dom', 'xml', 'intl', 'opcache', 'curl'] as $requirement) {
            $this->assertStringContainsString($requirement, $docker);
        }
        $this->assertMatchesRegularExpression('/composer install[^\n]*--no-dev[^\n]*--no-scripts/', $docker);
        $this->assertStringContainsString('--optimize-autoloader', $docker);
        $this->assertStringContainsString('APP_ENV=production', $docker);
        $this->assertStringContainsString('APP_DEBUG=false', $docker);
        $this->assertStringContainsString('CMD ["apache2-foreground"]', $docker);
        $this->assertDoesNotMatchRegularExpression('/artisan|key:generate|migrat|config:cache|npm|node|COPY[^\n]*\.env|(?:ARG|ENV)\s+APP_KEY/i', $docker);
    }

    public function test_apache_exposes_only_public_with_rewrites_and_no_directory_listing(): void
    {
        $docker = $this->source('Dockerfile');
        $apache = $this->source('deploy/apache.conf');
        $this->assertStringContainsString('a2enmod rewrite', $docker);
        $this->assertStringContainsString('deploy/apache.conf /etc/apache2/sites-available/000-default.conf', $docker);
        $this->assertStringContainsString('DocumentRoot /var/www/html/public', $apache);
        $this->assertStringContainsString('<Directory /var/www/html/public>', $apache);
        $this->assertStringContainsString('AllowOverride All', $apache);
        $this->assertStringContainsString('Options -Indexes', $apache);
        $this->assertStringContainsString('Require all granted', $apache);
        $this->assertStringContainsString('EXPOSE 80', $docker);
        $ini = parse_ini_string($this->source('deploy/php.ini'), scanner_mode: INI_SCANNER_RAW);
        $this->assertSame('Off', $ini['display_errors']);
        $this->assertSame('On', $ini['log_errors']);
        $this->assertSame('Off', $ini['expose_php']);
        $this->assertStringContainsString('deploy/php.ini /usr/local/etc/php/conf.d/clinic.ini', $docker);
    }

    public function test_runtime_directories_are_writable_and_health_checks_application_boot(): void
    {
        $docker = $this->source('Dockerfile');
        foreach (['storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/cache/locks', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $directory) {
            $this->assertStringContainsString($directory, $docker);
        }
        $this->assertStringContainsString('chown -R www-data:www-data storage bootstrap/cache', $docker);
        $this->assertStringContainsString('chmod -R u+rwX,g+rwX storage bootstrap/cache', $docker);
        $this->assertMatchesRegularExpression('/HEALTHCHECK[^\n]*--timeout=5s[^\n]*--retries=3/', $docker);
        $this->assertStringContainsString('curl --fail --silent --show-error --max-time 4 http://127.0.0.1/up', $docker);
    }

    public function test_context_is_allowlisted_and_excludes_secrets_and_local_state(): void
    {
        $rules = array_values(array_filter(array_map('trim', explode("\n", $this->source('.dockerignore'))), fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#')));
        $this->assertSame('**', $rules[0]);
        foreach (['!app/', '!app/**', '!bootstrap/', '!bootstrap/*.php', '!config/', '!config/**', '!database/', '!database/**', '!public/', '!public/**', '!resources/', '!resources/**', '!routes/', '!routes/**', '!deploy/', '!deploy/**', '!artisan', '!composer.json', '!composer.lock'] as $rule) {
            $this->assertContains($rule, $rules);
        }
        foreach (['**/.env*', '**/auth.json', '**/*.key', '**/*.pem', '**/*.log', 'public/storage', 'public/hot', 'bootstrap/cache'] as $rule) {
            $this->assertContains($rule, $rules);
        }
        foreach (['vendor', 'node_modules', 'tests', 'odd', '.git', '.ai', '.claude', 'storage'] as $excluded) {
            $this->assertNotContains('!'.$excluded.'/', $rules);
            $this->assertNotContains('!'.$excluded.'/**', $rules);
        }
    }

    public function test_source_packaging_keeps_existing_public_entrypoint_and_assets(): void
    {
        foreach (['artisan', 'composer.lock', 'bootstrap/app.php', 'bootstrap/providers.php', 'public/index.php', 'public/.htaccess', 'public/css/clinic.css', 'resources/views/home.blade.php', 'app/Modules/Appointments/routes/web.php'] as $path) {
            $this->assertFileExists(dirname(__DIR__, 2).'/'.$path);
        }
        $this->assertStringContainsString('RewriteRule ^ index.php [L]', $this->source('public/.htaccess'));
        $this->assertStringContainsString("health: '/up'", $this->source('bootstrap/app.php'));
    }
}
