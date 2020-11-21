<?php declare(strict_types=1);

namespace interpreter;


final class InvalidCodePath extends \Exception {}
final class ParseError extends \Exception {}


function main(): void
{
    require 'lexer.php';
    require 'parser.php';
    require 'interpreter.php';

    while (true)
    {
        $input = \readline('calc> ');
        if ($input)
        {
            $program = parse_expression(new Lexer($input));
            $result = interpret($program);
            echo "{$result}\n";
        }
    }
}
