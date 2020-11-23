<?php declare(strict_types=1);

namespace interpreter;


final class InvalidCodePath extends \Exception {}
final class ParseError extends \Exception {}


/**
 * @param string[] $argv
 */
function main(int $argc, array $argv): void
{
    if (2 !== $argc)
    {
        throw new \Exception('Source code file is required');
    }

    $file = $argv[1];
    if (!\is_file($file))
    {
        throw new \Exception("File {$file} does not exist");
    }

    $input = \file_get_contents($file);
    if ($input)
    {
        require 'lexer.php';
        require 'parser.php';
        require 'interpreter.php';

        $program = parse_program(new Lexer($input));
        $state = array();
        interpret($program, $state);

        foreach ($state as $key => $value)
        {
            \printf("%s: %s\n", $key, \var_export($value, true));
        }
    }
}
