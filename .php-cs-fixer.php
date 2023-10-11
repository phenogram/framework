<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->registerCustomFixers(new \ErickSkrauch\PhpCsFixer\Fixers())
    ->setRules([
        '@Symfony' => true,
        'yoda_style' => false,
        'concat_space' => false,
        'ErickSkrauch/blank_line_before_return' => true,
        'ErickSkrauch/line_break_after_statements' => true,
    ])
    ->setFinder($finder)
    ;
