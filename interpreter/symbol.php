<?php declare(strict_types=1);

namespace interpreter;


class NameError extends \Exception {}


abstract class Symbol {}


final class BuiltInSymbol extends Symbol
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }


    public function name(): string
    {
        return $this->name;
    }
}


final class VariableSymbol extends Symbol
{
    private string $name;
    private Symbol $type;

    public function __construct(string $name, Symbol $type)
    {
        $this->name = $name;
        $this->type = $type;
    }


    public function name(): string
    {
        return $this->name;
    }
}



function check_symbols(Program $program): void
{
    $symbols = [
        'INTEGER' => new BuiltInSymbol('INTEGER'),
        'REAL' => new BuiltInSymbol('REAL'),
    ];
    check_statement($program->statements(), $symbols);
}


/**
 * @param array<string, Symbol> $symbols
 */
function check_statement(Statement $statement, array &$symbols): void
{
    if ($statement instanceof Block)
    {
        foreach ($statement->declarations() as $declaration)
        {
            check_declaration($declaration, $symbols);
        }
        check_statement($statement->statements(), $symbols);
    }

    elseif ($statement instanceof CompoundStatement)
    {
        foreach ($statement->statements() as $statement)
        {
            check_statement($statement, $symbols);
        }
    }

    elseif ($statement instanceof Assignment)
    {
        check_variable($statement->variable(), $symbols);
        check_expression($statement->expression(), $symbols);
    }

    else
    {
        $syntax = \get_class($statement);
        throw new InvalidCodePath("Unknown statement: {$syntax}");
    }
}


/**
 * @param array<string, Symbol> $symbols
 */
function check_declaration(Declaration $declaration, array &$symbols): void
{
    $type = $declaration->type()->name();
    if (isset($symbols[$type]))
    {
        $type = $symbols[$type];
        $identifier = $declaration->variable()->identifier();
        $symbols[$identifier] = new VariableSymbol($identifier, $type);
    }
    else
    {
        throw new NameError("Unknown type {$type}");
    }
}


/**
 * @param array<string, Symbol> $symbols
 */
function check_expression(Expression $expression, array &$symbols): void
{
    if ($expression instanceof BinaryOperation)
    {
        check_expression($expression->left(), $symbols);
        check_expression($expression->right(), $symbols);
    }

    elseif ($expression instanceof UnaryOperation)
    {
        check_expression($expression->expression(), $symbols);
    }

    elseif ($expression instanceof Number)
    {
        // nothing to do
    }


    elseif ($expression instanceof Variable)
    {
        check_variable($expression, $symbols);
    }

    else
    {
        throw new InvalidCodePath(
            \sprintf(
                'Unknown expression: %s',
                \get_class($expression)
            )
        );
    }
}


/**
 * @param array<string, Symbol> $symbols
 */
function check_variable(Variable $variable, array &$symbols): void
{
    $identifier = $variable->identifier();
    if (!isset($symbols[$identifier]))
    {
        throw new NameError("Undeclared variable: {$identifier}");
    }
}
