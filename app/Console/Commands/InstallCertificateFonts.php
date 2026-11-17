<?php

namespace App\Console\Commands;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Console\Command;

/**
 * Registers the Certificate of Accreditation's typefaces (DOLE-OSHC template:
 * Copperplate Gothic Bold, Arial Black, Bodoni MT, Script MT Bold, Times New
 * Roman, Calibri and Arial Narrow) into dompdf's font cache in storage/fonts.
 *
 * Run it on a Windows machine that has the fonts installed; the generated files
 * in storage/fonts are committed, so the server (Linux) needs no fonts of its own.
 * Each style is read from its own TTF, so a regular weight really is regular.
 *
 * Only the styles the certificates use are stored (accreditation-certificate.css).
 * A family's other styles point at one of those files instead of a copy of their
 * own: dompdf switches to a different font altogether when a style is missing.
 */
class InstallCertificateFonts extends Command
{
    protected $signature   = 'dompdf:install-certificate-fonts';
    protected $description = 'Register the certificate fonts (Copperplate Gothic Bold, Arial Black, Times New Roman, Bodoni MT, Script MT Bold, Calibri, Arial Narrow) into the dompdf font cache';

    private const WINDOWS_FONTS = 'C:/Windows/Fonts';

    /** CSS family => [style => TTF file in C:/Windows/Fonts] — the styles the certificates use. */
    private array $fonts = [
        // Single-weight faces.
        'Copperplate Gothic Bold' => ['normal' => 'COPRGTB.TTF'],   // title
        'Arial Black'             => ['normal' => 'ariblk.ttf'],    // type / field / CONSULTANT
        'Script MT Bold'          => ['normal' => 'SCRIPTBL.TTF'],  // body text

        // Number, validity, "This certifies that" and the signatory (dompdf's
        // built-in Times is only a look-alike).
        'Times New Roman' => ['normal' => 'times.ttf', 'bold' => 'timesbd.ttf', 'italic' => 'timesi.ttf'],

        'Bodoni MT'    => ['normal' => 'BOD_R.TTF', 'bold' => 'BOD_B.TTF'],      // the name (bold)
        'Calibri'      => ['normal' => 'calibri.ttf', 'bold' => 'calibrib.ttf'], // backs, "as", footers
        'Arial Narrow' => ['normal' => 'ARIALN.TTF'],                            // FATPro footer
    ];

    /** A style a family lacks uses the first of these that it has. */
    private const STYLE_FALLBACKS = [
        'normal'      => ['bold', 'italic', 'bold_italic'],
        'bold'        => ['normal', 'bold_italic', 'italic'],
        'italic'      => ['normal', 'bold_italic', 'bold'],
        'bold_italic' => ['bold', 'italic', 'normal'],
    ];

    public function handle(): int
    {
        $fontDir = storage_path('fonts');
        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0775, true);
        }

        $options = new Options();
        $options->setFontDir($fontDir);
        $options->setFontCache($fontDir);
        $options->setChroot([base_path(), self::WINDOWS_FONTS, str_replace('/', '\\', self::WINDOWS_FONTS)]);

        $fontMetrics = (new Dompdf($options))->getFontMetrics();

        foreach ($this->fonts as $family => $styles) {
            foreach ($styles as $style => $file) {
                $ttf = $this->source($family, $style, $file, $fontDir);
                if (! $ttf) {
                    $this->warn("  ⚠  {$family} ({$style}): {$file} not found, skipping");
                    continue;
                }

                $this->line("  → Registering [{$family}] ({$style}) from " . basename($ttf));

                $registered = $fontMetrics->registerFont([
                    'family' => $family,
                    'weight' => str_contains($style, 'bold') ? 'bold' : 'normal',
                    'style'  => str_contains($style, 'italic') ? 'italic' : 'normal',
                ], $ttf);

                if (! $registered) {
                    $this->error("      ✖ Failed to register {$ttf}");
                }
            }
        }

        $fontMetrics->saveFontFamilies();

        $installed = $this->normalizeInstalledFonts($fontDir);
        $this->removeStaleFiles($fontDir, $installed);

        $this->info('✔ Certificate fonts registered in ' . $fontDir);
        $this->info('  Clear any cached views: php artisan view:clear');

        return self::SUCCESS;
    }

    /**
     * The TTF for one style: the Windows font, or else the copy already in
     * storage/fonts for exactly this family and style.
     */
    private function source(string $family, string $style, string $file, string $fontDir): ?string
    {
        $windows = self::WINDOWS_FONTS . '/' . $file;
        if (file_exists($windows)) {
            return $windows;
        }

        $pattern = '/^' . preg_quote($this->prefix($family), '/') . '_' . $style . '_[0-9a-f]{32}\.ttf$/';
        foreach (scandir($fontDir) ?: [] as $name) {
            if (preg_match($pattern, $name)) {
                return $fontDir . '/' . $name;
            }
        }

        return null;
    }

    /**
     * installed-fonts.json with file names only (no absolute paths), so the
     * committed cache works on any machine. For these families it keeps only
     * the styles registered now, and every other style points at one of them.
     * Returns the referenced names.
     */
    private function normalizeInstalledFonts(string $fontDir): array
    {
        $path = $fontDir . '/installed-fonts.json';
        $json = file_exists($path) ? json_decode(file_get_contents($path), true) : null;
        if (! is_array($json)) {
            return [];
        }

        $managed = [];
        foreach ($this->fonts as $family => $styles) {
            $managed[mb_strtolower($family)] = array_keys($styles);
        }

        $clean = [];
        foreach ($json as $family => $variants) {
            foreach ($variants as $style => $file) {
                if (! isset($managed[$family]) || in_array($style, $managed[$family], true)) {
                    $clean[$family][$style] = basename($file);
                }
            }

            if (isset($managed[$family], $clean[$family])) {
                foreach (self::STYLE_FALLBACKS as $style => $fallbacks) {
                    if (isset($clean[$family][$style])) {
                        continue;
                    }
                    foreach ($fallbacks as $fallback) {
                        if (isset($clean[$family][$fallback])) {
                            $clean[$family][$style] = $clean[$family][$fallback];
                            break;
                        }
                    }
                }
                ksort($clean[$family]);
            }
        }
        file_put_contents($path, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return collect($clean)->flatten()->all();
    }

    /** Delete earlier copies of these families that installed-fonts.json no longer uses. */
    private function removeStaleFiles(string $fontDir, array $installed): void
    {
        $prefixes = implode('|', array_map(fn ($family) => preg_quote($this->prefix($family), '/'), array_keys($this->fonts)));
        $pattern  = '/^((?:' . $prefixes . ')_(?:normal|bold|italic|bold_italic)_[0-9a-f]{32})\.(?:ttf|ufm|ufm\.json|afm|afm\.json)$/';

        foreach (scandir($fontDir) ?: [] as $name) {
            if (preg_match($pattern, $name, $m) && ! in_array($m[1], $installed, true)) {
                unlink($fontDir . '/' . $name);
                $this->line("  − Removed stale {$name}");
            }
        }
    }

    /** dompdf's file-name prefix for a family ("Bodoni MT" → "bodoni_mt"). */
    private function prefix(string $family): string
    {
        return preg_replace('/\W/', '_', mb_strtolower($family));
    }
}
