<?php declare(strict_types=1);

namespace interpreter;


/**
 * @return int|float
 */
function interpret(Ast $program)
{
    if ($program instanceof BinaryOperation)
    {
        $operation = $program->operation();
        $left = interpret($program->left());
        $right = interpret($program->right());

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

        $operation = TokenType::name($operation);
        throw new InvalidCodePath("Unexpected binary operation: {$operation}");
    }

    if ($program instanceof UnaryOperation)
    {
        $operation = $program->operation();
        $value = interpret($program->expression());

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

    $syntax = \get_class($program);
    throw new InvalidCodePath("Unknown syntax: {$syntax}");
}
