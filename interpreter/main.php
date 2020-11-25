<?php declare(strict_types=1);

namespace interpreter;


final class InvalidCodePath extends \Exception {}


abstract class Error extends \Exception
{
    private int $col;

    public function __construct(string $message, int $line, int $col)
    {
        parent::__construct($message);
        $this->line = $line;
        $this->col = $col;
    }

    public function __toString(): string
    {
        return \sprintf(
            '%s: %s at %d:%s',
            \get_class($this),
            $this->message,
            $this->line,
            $this->col
        );
    }
}

final class LexicalError extends Error {}
final class ParseError extends Error {}
final class SemanticError extends Error {}



/**
 * @return never
 */
function unexpected_token(Token $token)
{
    throw new ParseError(
        "Unexpected token: {$token}",
        $token->line(),
        $token->col()
    );
}


/**
 * @return never
 */
function unknown_token(string $token, int $line, int $col)
{
    throw new LexicalError("Unknown token '{$token}'", $line, $col);
}


/**
 * @return never
 */
function unclosed_comment(int $line, int $col)
{
    throw new LexicalError('Unclosed comment beginning', $line, $col);
}


/**
 * @return never
 */
function undeclared_identifier(Token $token)
{
    throw new SemanticError(
        "Undeclared identifier: {$token}",
        $token->line(),
        $token->col()
    );
}


/**
 * @return never
 */
function duplicate_identifier(Token $token)
{
    throw new SemanticError(
        "Redefinition of identifier: {$token}",
        $token->line(),
        $token->col()
    );
}


/**
 * @return never
 */
function invalid_procedure(Token $token)
{
    throw new SemanticError(
        "Identifier is not a procedure: {$token}",
        $token->line(),
        $token->col()
    );
}


/**
 * @return never
 */
function argument_mismatch(Token $token, int $nargs, int $nparams)
{
    throw new SemanticError(
        "Procedure '{$token->value()}' called with {$nargs} arguments but takes {$nparams}",
        $token->line(),
        $token->col()
    );
}


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
        require 'analyzer.php';
        require 'interpreter.php';
        require 'lexer.php';
        require 'parser.php';

        $state = array();
        run_code($input, $state);

        foreach ($state as $key => $value)
        {
            \printf("%s: %s\n", $key, \var_export($value, true));
        }
    }
}


/**
 * @param array<string, mixed> $state
 * @return mixed
 */
function run_code(string $code, array &$state)
{
    $program = parse_program(new Lexer($code));
    check_program($program);
    interpret($program, $state);
}
