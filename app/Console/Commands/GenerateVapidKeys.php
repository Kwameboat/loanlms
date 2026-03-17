<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateVapidKeys extends Command
{
    protected $signature   = 'webpush:vapid';
    protected $description = 'Generate VAPID keys for Web Push Notifications and save to .env';

    public function handle(): int
    {
        $this->info('Generating VAPID keys for Web Push Notifications...');

        // Use OpenSSL to generate EC P-256 key pair
        $key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if (! $key) {
            $this->error('Failed to generate key pair. Ensure OpenSSL is installed with EC support.');
            return Command::FAILURE;
        }

        $details    = openssl_pkey_get_details($key);
        $privateKey = $details['ec']['d'];
        $publicKey  = "\x04" . $details['ec']['x'] . $details['ec']['y'];

        $publicKeyB64  = rtrim(strtr(base64_encode($publicKey), '+/', '-_'), '=');
        $privateKeyB64 = rtrim(strtr(base64_encode($privateKey), '+/', '-_'), '=');

        $this->line('');
        $this->info('VAPID Keys Generated:');
        $this->line('');
        $this->line('  <comment>Public Key:</comment>');
        $this->line("  {$publicKeyB64}");
        $this->line('');
        $this->line('  <comment>Private Key:</comment>');
        $this->line("  {$privateKeyB64}");
        $this->line('');

        // Write to .env
        $envPath = base_path('.env');

        if (File::exists($envPath)) {
            $env = File::get($envPath);

            // Replace or append
            $replacements = [
                'VAPID_PUBLIC_KEY'  => $publicKeyB64,
                'VAPID_PRIVATE_KEY' => $privateKeyB64,
            ];

            foreach ($replacements as $key => $value) {
                if (str_contains($env, "{$key}=")) {
                    $env = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $env);
                } else {
                    $env .= "\n{$key}={$value}";
                }
            }

            File::put($envPath, $env);
            $this->info('VAPID keys written to .env file.');
        } else {
            $this->warn('.env file not found. Add these to your .env manually:');
        }

        $this->line('');
        $this->line('  Add to your <comment>.env</comment>:');
        $this->line("  VAPID_PUBLIC_KEY={$publicKeyB64}");
        $this->line("  VAPID_PRIVATE_KEY={$privateKeyB64}");
        $this->line("  VAPID_SUBJECT=mailto:your@email.com");
        $this->line('');
        $this->info('Also add the public key to your layout so the PWA client can subscribe:');
        $this->line("  window.KOBOFLOW_VAPID_KEY = '{$publicKeyB64}'");
        $this->line('');

        return Command::SUCCESS;
    }
}
