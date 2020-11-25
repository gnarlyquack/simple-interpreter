<?php declare(strict_types=1);

namespace interpreter;


class NameError extends \Exception {}



abstract class Symbol
{
    abstract public function __toString(): string;
}


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


    public function __toString(): string
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


    public function __toString(): string
    {
        return "{$this->name}: {$this->type}";
    }
}


final class ProcedureSymbol extends Symbol
{
    private string $name;
    /** @var VariableDeclaration[] */
    private array $parameters;

    /**
     * @param VariableDeclaration[] $parameters
     */
    public function __construct(string $name, array $parameters)
    {
        $this->name = $name;
        $this->parameters = $parameters;
    }


    public function __toString(): string
    {
        $parameters = \implode('; ', $this->parameters);
        return "{$this->name}({$parameters})";
    }
}



final class SymbolTable
{
    private int $scope = 0;
    /** @var array<string, Symbol>[] */
    private array $scopes = [];

    public function __construct()
    {
        $this->scopes[] = [];
        $this->add_builtin('INTEGER');
        $this->add_builtin('REAL');
    }


    public function add_variable(string $name, string $type): void
    {
        $scope = $this->scope;
        $lower = \strtolower($name);
        if (isset($this->scopes[$scope][$lower]))
        {
            throw new NameError("Redefinition of name: {$name}");
        }
        $type = $this->lookup($type);

        $symbol = new VariableSymbol($name, $type);
        // echo "Scope {$this->scope}: adding symbol {$symbol}\n";
        $this->scopes[$scope][$lower] = $symbol;
    }


    /**
     * @param VariableDeclaration[] $parameters
     */
    public function add_procedure(string $name, array $parameters): void
    {
        $scope = $this->scope;
        $lower = \strtolower($name);
        if (isset($this->scopes[$scope][$lower]))
        {
            throw new NameError("Redefinition of name: {$name}");
        }

        $symbol = new ProcedureSymbol($name, $parameters);
        // echo "Scope {$this->scope}: adding symbol {$symbol}\n";
        $this->scopes[$scope][$lower] = $symbol;
    }


    public function lookup(string $name): Symbol
    {
        $lower = \strtolower($name);
        for ($scope = $this->scope; $scope >= 0; --$scope)
        {
            if (isset($this->scopes[$scope][$lower]))
            {
                $symbol = $this->scopes[$scope][$lower];
                // echo "Scope {$scope}: Found symbol {$symbol}\n";
                return $symbol;
            }
        }
        throw new NameError("Undeclared name: {$name}");
    }


    public function push_scope(): void
    {
        $this->scopes[] = [];
        ++$this->scope;
    }


    public function pop_scope(): void
    {
        \array_pop($this->scopes);
        --$this->scope;
    }


    private function add_builtin(string $name): void
    {
        // echo "Adding built-in symbol '{$name}'\n";
        $this->scopes[0][\strtolower($name)] = new BuiltInSymbol($name);
    }
}



function check_program(Program $program): void
{
    $symbols = new SymbolTable;
    check_statement($program->statements(), $symbols);
}


function check_statement(Statement $statement, SymbolTable $symbols): void
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
        $type = \get_class($statement);
        throw new InvalidCodePath("Unknown statement type: {$type}");
    }
}


function check_declaration(Declaration $declaration, SymbolTable $symbols): void
{
    if ($declaration instanceof VariableDeclaration)
    {
        $identifier = $declaration->variable()->identifier();
        $type = $declaration->type()->name();
        $symbols->add_variable($identifier, $type);
    }

    elseif ($declaration instanceof ProcedureDeclaration)
    {
        $name = $declaration->name()->identifier();
        $parameters = $declaration->parameters();
        $symbols->add_procedure($name, $parameters);

        $symbols->push_scope();
        foreach ($parameters as $parameter)
        {
            check_declaration($parameter, $symbols);
        }
        check_statement($declaration->body(), $symbols);
        $symbols->pop_scope();
    }

    else
    {
        $type = \get_class($declaration);
        throw new InvalidCodePath("Unknown declaration type: {$type}");
    }
}


function check_expression(Expression $expression, SymbolTable $symbols): void
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
        $type = \get_class($expression);
        throw new InvalidCodePath("Unknown expression type: {$type}");
    }
}


function check_variable(Variable $variable, SymbolTable $symbols): void
{
    $symbols->lookup($variable->identifier());
}