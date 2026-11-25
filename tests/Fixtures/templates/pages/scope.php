<?php

declare(strict_types=1);

/**
 * Elenca le variabili visibili al template.
 */
$variables = array_keys(get_defined_vars());
sort($variables);

echo implode(',', $variables);
