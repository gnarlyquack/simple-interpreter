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
        $state->peek_frame()->set($identifier, $value);
    }

    elseif ($statement instanceof ProcedureCall)
    {
        $name = $statement->name()->name();
        $arguments = $statement->arguments();
        $procedure = $statement->procedure;
        $frame = new Frame($name, $procedure->scope() + 1);
        foreach ($procedure->parameters() as $i => $parameter)
        {
            $name = $parameter->variable()->name();
            $argument = interpret_expression($arguments[$i], $state);
            $frame->set($name, $argument);
        }
        $state->push_frame($frame);
        interpret_statement($procedure->body(), $state);
        $state->pop_frame();
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
        return $state->peek_frame()->lookup($variable);
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
    /** @var Frame[] */
    private array $frames = [];


    public function __construct()
    {
        $this->frames[] = new Frame('GLOBAL', 0);
    }

    public function push_frame(Frame $frame): void
    {
        $this->frames[] = $frame;
        // echo "push frame {$frame->name()} (scope {$frame->scope()})\n";
    }


    public function pop_frame(): Frame
    {
        \assert(\count($this->frames) > 0);
        $frame = \array_pop($this->frames);
        // echo "pop frame {$frame->name()}\n";
        return $frame;
    }


    public function peek_frame(): Frame
    {
        \assert(\count($this->frames) > 0);
        return \end($this->frames);
    }
}


final class Frame
{
    private string $name;
    private int $scope;
    /** @var array<string, mixed> */
    private array $variables = [];


    public function __construct(string $name, int $scope)
    {
        $this->name = $name;
        $this->scope = $scope;
    }

    /**
     * @param mixed $value
     */
    public function set(string $name, $value): void
    {
        // echo "setting {$name}: ", \var_export($value, true), "\n";
        $this->variables[$name] = $value;
    }

    /**
     * @return mixed
     */
    public function lookup(string $name)
    {
        $value = $this->variables[$name];
        // echo "reading {$name}: ", \var_export($value, true), "\n";
        return $value;
    }


    public function name(): string
    {
        return $this->name;
    }


    public function scope(): int
    {
        return $this->scope;
    }

    /** @return array<string, mixed> */
    public function variables(): array
    {
        return $this->variables;
    }
}
