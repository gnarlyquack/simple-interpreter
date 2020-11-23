<?php declare(strict_types=1);

namespace interpreter;


/**
 * @param array<string, mixed> $state
 * @return mixed
 */
function interpret(Ast $program, array &$state)
{
    if ($program instanceof CompoundStatement)
    {
        foreach ($program->statements() as $statement)
        {
            interpret($statement, $state);
        }
        return;
    }

    if ($program instanceof Assignment)
    {
        $variable = $program->variable();
        $value = interpret($program->expression(), $state);
        $state[$variable] = $value;
        return;
    }

    if ($program instanceof BinaryOperation)
    {
        $operation = $program->operation();
        $left = interpret($program->left(), $state);
        $right = interpret($program->right(), $state);

        if (TokenType::TOKEN_PLUS === $operation)
        {
            return $left + $right;
        }
        if (TokenType::TOKEN_MINUS === $operation)
        {
            return $left - $right;
        }
        if (TokenType::TOKEN_DIV === $operation)
        {
            return $left / $right;
        }
        if (TokenType::TOKEN_MUL === $operation)
        {
            return $left * $right;
        }
        if (TokenType::TOKEN_INTDIV === $operation)
        {
            return \intdiv($left, $right);
        }

        $operation = TokenType::name($operation);
        throw new InvalidCodePath("Unexpected binary operation: {$operation}");
    }

    if ($program instanceof UnaryOperation)
    {
        $operation = $program->operation();
        $value = interpret($program->expression(), $state);

        if (TokenType::TOKEN_PLUS === $operation)
        {
            return $value;
        }
        if (TokenType::TOKEN_MINUS === $operation)
        {
            return -$value;
        }

        $operation = TokenType::name($operation);
        throw new InvalidCodePath("Unexpected unary operation: {$operation}");
    }

    if ($program instanceof Number)
    {
        return $program->value();
    }


    if ($program instanceof Variable)
    {
        $variable = $program->identifier();
        if (\array_key_exists($variable, $state))
        {
            return $state[$variable];
        }
        throw new \Exception("Undeclared variable {$variable}");
    }

    $syntax = \get_class($program);
    throw new InvalidCodePath("Unknown syntax: {$syntax}");
}
