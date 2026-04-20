<?php
/**
 * @package WebcafeinaReservas
 */

declare(strict_types=1);

namespace WebcafeinaReservas\Services;

/**
 * Abstraction over the PDF engine (FPDI / FPDM / …). Keeps PdfGenerator
 * testable and lets us swap implementations without touching the rest of
 * the codebase. See ADR 002 (to be written in Phase 7).
 */
interface PdfFillerInterface {

    /**
     * Fill an AcroForm template and return the result as a raw PDF byte stream.
     *
     * @param string               $templatePath  absolute path to the source PDF.
     * @param array<string, string> $fields        map of AcroForm field name → value.
     *
     * @return string raw PDF bytes.
     */
    public function fill( string $templatePath, array $fields ): string;
}
