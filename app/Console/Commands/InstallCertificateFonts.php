<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Dompdf\Dompdf;
use Dompdf\Options;

class InstallCertificateFonts extends Command
{
    protected $signature   = 'dompdf:install-certificate-fonts';
    protected $description = 'Register Copperplate Gothic Bold, Bodoni MT, Calibri, Arial Narrow, and Script MT Bold into dompdf font cache';

    /**
     * Each entry:
     *   css_name  – the font-family name used in CSS
     *   style     – normal | bold | italic | bold_italic
     *   ttf_path  – absolute path to the TTF file on this server
     */
    private array $fonts = [
        // ── Copperplate Gothic Bold ──────────────────────────────────────────
        ['css_name' => 'Copperplate Gothic Bold', 'style' => 'normal', 'ttf_path' => 'C:/Windows/Fonts/COPRGTB.TTF'],
        ['css_name' => 'Copperplate Gothic Bold', 'style' => 'bold',   'ttf_path' => 'C:/Windows/Fonts/COPRGTB.TTF'],

        // ── Bodoni MT ────────────────────────────────────────────────────────
        ['css_name' => 'Bodoni MT', 'style' => 'normal',     'ttf_path' => 'C:/Windows/Fonts/BOD_R.TTF'],
        ['css_name' => 'Bodoni MT', 'style' => 'bold',       'ttf_path' => 'C:/Windows/Fonts/BOD_B.TTF'],
        ['css_name' => 'Bodoni MT', 'style' => 'italic',     'ttf_path' => 'C:/Windows/Fonts/BOD_I.TTF'],
        ['css_name' => 'Bodoni MT', 'style' => 'bold_italic', 'ttf_path' => 'C:/Windows/Fonts/BOD_BI.TTF'],

        // ── Calibri ──────────────────────────────────────────────────────────
        ['css_name' => 'Calibri', 'style' => 'normal',     'ttf_path' => 'C:/Windows/Fonts/calibri.ttf'],
        ['css_name' => 'Calibri', 'style' => 'bold',       'ttf_path' => 'C:/Windows/Fonts/calibrib.ttf'],
        ['css_name' => 'Calibri', 'style' => 'italic',     'ttf_path' => 'C:/Windows/Fonts/calibrii.ttf'],
        ['css_name' => 'Calibri', 'style' => 'bold_italic', 'ttf_path' => 'C:/Windows/Fonts/calibriz.ttf'],

        // ── Arial Narrow ─────────────────────────────────────────────────────
        ['css_name' => 'Arial Narrow', 'style' => 'normal',     'ttf_path' => 'C:/Windows/Fonts/ARIALN.TTF'],
        ['css_name' => 'Arial Narrow', 'style' => 'bold',       'ttf_path' => 'C:/Windows/Fonts/ARIALNB.TTF'],
        ['css_name' => 'Arial Narrow', 'style' => 'italic',     'ttf_path' => 'C:/Windows/Fonts/ARIALNI.TTF'],
        ['css_name' => 'Arial Narrow', 'style' => 'bold_italic', 'ttf_path' => 'C:/Windows/Fonts/ARIALNBI.TTF'],

        // ── Script MT Bold ───────────────────────────────────────────────────
        ['css_name' => 'Script MT Bold', 'style' => 'normal',     'ttf_path' => 'C:/Windows/Fonts/SCRIPTBL.TTF'],
        ['css_name' => 'Script MT Bold', 'style' => 'bold',       'ttf_path' => 'C:/Windows/Fonts/SCRIPTBL.TTF'],
        ['css_name' => 'Script MT Bold', 'style' => 'italic',     'ttf_path' => 'C:/Windows/Fonts/SCRIPTBL.TTF'],
        ['css_name' => 'Script MT Bold', 'style' => 'bold_italic', 'ttf_path' => 'C:/Windows/Fonts/SCRIPTBL.TTF'],
    ];

    public function handle(): int
    {
        $fontDir   = storage_path('fonts');
        $cacheDir  = storage_path('fonts');

        // Ensure the directory is writable
        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0775, true);
        }

        $options = new Options();
        $options->setFontDir($fontDir);
        $options->setFontCache($cacheDir);
        $options->setChroot([base_path(), 'C:/Windows/Fonts', 'C:\Windows\Fonts']);

        $dompdf = new Dompdf($options);
        $fontMetrics = $dompdf->getFontMetrics();

        foreach ($this->fonts as $font) {
            $ttf = $font['ttf_path'];

            if (! file_exists($ttf)) {
                $this->warn("  ⚠  TTF not found, skipping: {$ttf}");
                continue;
            }

            $this->line("  → Registering [{$font['css_name']}] ({$font['style']})");

            $success = $fontMetrics->registerFont(
                [
                    'family' => $font['css_name'],
                    'style'  => $font['style'] === 'bold_italic' ? 'bold italic' : $font['style'],
                    'weight' => str_contains($font['style'], 'bold') ? 'bold' : 'normal',
                ],
                $ttf
            );

            if (!$success) {
                $this->error("      ✖ Failed to register font: {$ttf}");
            }
        }

        $fontMetrics->saveFontFamilies();

        $this->info('✔ All certificate fonts registered in ' . $fontDir);
        $this->info('  Clear any cached views: php artisan view:clear');

        return self::SUCCESS;
    }
}
