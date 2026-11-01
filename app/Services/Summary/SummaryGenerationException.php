<?php

namespace App\Services\Summary;

use RuntimeException;

/**
 * Thrown by an LLM-backed generator when it cannot produce a usable summary.
 * FallbackSummaryGenerator catches this and uses the rules-based engine instead.
 */
final class SummaryGenerationException extends RuntimeException {}
