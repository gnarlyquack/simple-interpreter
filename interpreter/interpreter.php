<?php declare(strict_types=1);

namespace interpreter;


/**
 * @param array<string, mixed> $state
 */
function interpret(Program $program, array &$state): void
{
    interpret_statement($program->statements(), $state);
}


/**
 * @param array<string, mixed> $state
 */
function interpret_statement(Statement $statement, array &$state): void
{
    if ($statement instanceof Block)
    {
        foreach ($statement->declarations() as $declaration)
        {
            interpret_statement($declaration, $state);
        }
        interpret_statement($statement->statements(), $state);
    }

    elseif ($statement instanceof Declaration)
    {
        // do nothing, for now
    }

    elseif ($statement instanceof CompoundStatement)
    {
        foreach ($statement->statements() as $statement)
        {
            interpret_statement($statement, $state);
        }
    }

    elseif ($statement instanceof Assignment)
    {
        $variable = $statement->variable();
        $value = interpret_expression($statement->expression(), $state);
        $state[$variable] = $value;
    }
    else
    {
        $syntax = \get_class($statement);
        throw new InvalidCodePath("Unknown statement: {$syntax}");
    }
}


/**
 * @param array<string, mixed> $state
 * @return mixed
 */
function interpret_expression(Expression $expression, array &$state)
{
    if ($expression instanceof BinaryOperation)
    {
        $operation = $expression->operation();
        $left = interpret_expression($expression->left(), $state);
        $right = interpret_expression($expression->right(), $state);

        if (TokenType::TOKEN_PLUS === $operation)
        {
            return $left + $right;
        }
        elseif (TokenType::TOKEN_MINUS === $operation)
        {
            return $left - $right;
        }
        elseif (TokenType::TOKEN_DIV === $operation)
        {
            return $left / $right;
        }
        elseif (TokenType::TOKEN_MUL === $operation)
        {
            return $left * $right;
        }
        elseif (TokenType::TOKEN_INTDIV === $operation)
        {
            return \intdiv($left, $right);
        }
        else
        {
            throw new InvalidCodePath(
                \sprintf(
                    'Unexpected binary operation: %s',
                    TokenType::name($operation)
                )
            );
        }
    }

    elseif ($expression instanceof UnaryOperation)
    {
        $operation = $expression->operation();
        $value = interpret_expression($expression->expression(), $state);

        if (TokenType::TOKEN_PLUS === $operation)
        {
            return $value;
        }
        elseif (TokenType::TOKEN_MINUS === $operation)
        {
            return -$value;
        }
        else
        {
            throw new InvalidCodePath(
                \sprintf(
                    'Unexpected unary operation: %s',
                    TokenType::name($operation)
                )
            );
        }
    }

    elseif ($expression instanceof Number)
    {
        return $expression->value();
    }


    elseif ($expression instanceof Variable)
    {
        $variable = $expression->identifier();
        if (\array_key_exists($variable, $state))
        {
            return $state[$variable];
        }
        else
        {
            throw new \Exception("Undeclared variable {$variable}");
        }
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
