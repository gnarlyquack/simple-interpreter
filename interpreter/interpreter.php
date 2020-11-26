<?php declare(strict_types=1);

namespace interpreter;


function interpret(Program $program): void
{
    $state = new Memory;
    interpret_program($program, $state);
}


function interpret_program(Program $program, Memory $state): void
{
    interpret_statement($program->statements(), $state);
}


function interpret_statement(Statement $statement, Memory $state): void
{
    if ($statement instanceof Block)
    {
        interpret_statement($statement->statements(), $state);
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
        $identifier = $statement->variable()->name();
        $value = interpret_expression($statement->expression(), $state);
        $state->set($identifier, $value);
    }

    elseif ($statement instanceof ProcedureCall)
    {
    }

    else
    {
        $syntax = \get_class($statement);
        throw new InvalidCodePath("Unknown statement: {$syntax}");
    }
}


/**
 * @return mixed
 */
function interpret_expression(Expression $expression, Memory $state)
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
        $variable = $expression->name();
        return $state->lookup($variable);
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



final class Memory
{
    private int $frame = 0;
    /** @var array<string, mixed>[] */
    private array $frames = [];


    public function __construct()
    {
        $this->frames[] = [];
    }

    /**
     * @param mixed $value
     */
    public function set(string $name, $value): void
    {
        // echo "setting {$name}: ", \var_export($value, true), "\n";
        $this->frames[$this->frame][$name] = $value;
    }

    /**
     * @return mixed
     */
    public function lookup(string $name)
    {
        $value = $this->frames[$this->frame][$name];
        // echo "reading {$name}: ", \var_export($value, true), "\n";
        return $value;
    }


    public function push_frame(): void
    {
        $this->frames[] = [];
        ++$this->frame;
        // echo "push frame {$this->frame}\n";
    }


    public function pop_frame(): void
    {
        // echo "pop frame {$this->frame}\n";
        \array_pop($this->frames);
        --$this->frame;
    }


    /**
     * @return array<string, mixed>
     */
    public function peek_frame(): array
    {
        return $this->frames[$this->frame];
    }

}
