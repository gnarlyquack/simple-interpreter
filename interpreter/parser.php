<?php declare(strict_types=1);

namespace interpreter;


abstract class Ast {}


final class Program extends Ast
{
    private Variable $name;
    private Block $statements;

    public function __construct(Variable $name, Block $statements)
    {
        $this->name = $name;
        $this->statements = $statements;
    }


    public function name() : Variable
    {
        return $this->name;
    }


    public function statements(): Block
    {
        return $this->statements;
    }
}


abstract class Statement extends Ast {}


final class Block extends Statement
{
    /** @var Declaration[] */
    private array $declarations;
    private CompoundStatement $statements;

    /**
     * @param Declaration[] $declarations
     */
    public function __construct(
        array $declarations,
        CompoundStatement $statements)
    {
        $this->declarations = $declarations;
        $this->statements = $statements;
    }

    /**
     * @return Declaration[]
     */
    public function declarations(): array
    {
        return $this->declarations;
    }


    public function statements(): CompoundStatement
    {
        return $this->statements;
    }
}


abstract class Declaration extends Statement {}


final class VariableDeclaration extends Declaration
{
    private Variable $variable;
    private Type $type;

    public function __construct(Variable $variable, Type $type)
    {
        $this->variable = $variable;
        $this->type = $type;
    }


    public function variable(): Variable
    {
        return $this->variable;
    }


    public function type(): Type
    {
        return $this->type;
    }


    public function __toString(): string
    {
        return "{$this->variable->name()}: {$this->type->name()}";
    }
}


final class ProcedureDeclaration extends Declaration
{
    private Variable $name;
    /** @var VariableDeclaration[] */
    private array $parameters;
    private Block $body;

    /**
     * @param VariableDeclaration[] $parameters
     */
    public function __construct(Variable $name, array $parameters, Block $body)
    {
        $this->name = $name;
        $this->parameters = $parameters;
        $this->body = $body;
    }


    public function name(): Variable
    {
        return $this->name;
    }

    /**
     * @return VariableDeclaration[]
     */
    public function parameters(): array
    {
        return $this->parameters;
    }


    public function body(): Block
    {
        return $this->body;
    }
}


final class Type
{
    private Token $token;

    public function __construct(Token $token)
    {
        \assert(
            \in_array(
                $token->type(),
                [TokenType::TOKEN_INTEGER, TokenType::TOKEN_REAL]
            )
        );
        $this->token = $token;
    }

    public function name(): string
    {
        return $this->token->value();
    }

    public function token(): Token
    {
        return $this->token;
    }
}


final class CompoundStatement extends Statement
{
    /** @var Statement[] */
    private array $statements;

    /**
     * @param Statement[] $statements
     */
    public function __construct(array $statements)
    {
        $this->statements = $statements;
    }


    /**
     * @return Statement[]
     */
    public function statements(): array
    {
        return $this->statements;
    }
}


final class Assignment extends Statement
{
    private Variable $variable;
    private Expression $expression;

    public function __construct(Variable $variable, Expression $expression)
    {
        $this->variable = $variable;
        $this->expression = $expression;
    }


    public function variable(): Variable
    {
        return $this->variable;
    }


    public function expression(): Expression
    {
        return $this->expression;
    }
}


final class ProcedureCall extends Statement
{
    public ProcedureSymbol $procedure;
    private Variable $name;
    /** @var Expression[] */
    private array $arguments;

    /**
     * @param Expression[] $arguments
     */
    public function __construct(Variable $name, array $arguments)
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }


    public function name(): Variable
    {
        return $this->name;
    }


    /**
     * @return Expression[]
     */
    public function arguments(): array
    {
        return $this->arguments;
    }
}


abstract class Expression extends Ast {}


final class BinaryOperation extends Expression
{
    private Token $operation;
    private Expression $left;
    private Expression $right;

    public function __construct(Token $operation, Expression $left, Expression $right)
    {
        \assert(
            \in_array(
                $operation->type(),
                [
                    TokenType::TOKEN_PLUS, TokenType::TOKEN_MINUS,
                    TokenType::TOKEN_DIV, TokenType::TOKEN_MUL,
                    TokenType::TOKEN_INTDIV,
                ]
            )
        );
        $this->operation = $operation;
        $this->left = $left;
        $this->right = $right;
    }

    /**
     * @return TokenType::TOKEN_*
     */
    public function operation(): int
    {
        return $this->operation->type();
    }


    public function left(): Expression
    {
        return $this->left;
    }


    public function right(): Expression
    {
        return $this->right;
    }
}


final class UnaryOperation extends Expression
{
    private Token $operation;
    private Expression $expression;

    public function __construct(Token $operation, Expression $expression)
    {
        \assert(
            \in_array(
                $operation->type(),
                [TokenType::TOKEN_PLUS, TokenType::TOKEN_MINUS]
            )
        );
        $this->operation = $operation;
        $this->expression = $expression;
    }

    /**
     * @return TokenType::TOKEN_*
     */
    public function operation(): int
    {
        return $this->operation->type();
    }


    public function expression(): Expression
    {
        return $this->expression;
    }
}



final class Number extends Expression
{
    private Token $number;

    public function __construct(Token $number)
    {
        \assert(
            \in_array(
                $number->type(),
                [
                    TokenType::TOKEN_INTEGER_LITERAL,
                    TokenType::TOKEN_FLOAT_LITERAL,
                ]
            )
        );
        $this->number = $number;
    }


    /**
     * @return int|float
     */
    public function value()
    {
        return $this->number->value();
    }
}


final class Variable extends Expression
{
    private Token $variable;

    public function __construct(Token $variable)
    {
        \assert(TokenType::TOKEN_ID === $variable->type());
        $this->variable = $variable;
    }


    public function name(): string
    {
        return $this->variable->value();
    }


    public function token(): Token
    {
        return $this->variable;
    }
}



function parse_program(Lexer $lexer): Program
{
    $lexer->eat_token(TokenType::TOKEN_PROGRAM);
    $name = parse_variable($lexer);
    $lexer->eat_token(TokenType::TOKEN_SEMI);
    $block = parse_block($lexer);
    $lexer->eat_token(TokenType::TOKEN_DOT);

    return new Program($name, $block);
}


function parse_block(Lexer $lexer): Block
{
    $declarations = parse_declarations($lexer);
    $statements = parse_compound_statement($lexer);

    return new Block($declarations, $statements);
}


/**
 * @return Declaration[]
 */
function parse_declarations(Lexer $lexer): array
{
    $declarations = [];
    while ($lexer->peek_token(TokenType::TOKEN_VAR))
    {
        $lexer->eat_token();
        foreach (parse_variable_declaration($lexer) as $declaration)
        {
            $declarations[] = $declaration;
        }
        $lexer->eat_token(TokenType::TOKEN_SEMI);
    }

    while($lexer->peek_token(TokenType::TOKEN_PROCEDURE))
    {
        $declarations[] = parse_procedure_declaration($lexer);
    }

    return $declarations;
}


/**
 * @return VariableDeclaration[]
 */
function parse_variable_declaration(Lexer $lexer): array
{
    $ids = [];
    while (true)
    {
        $ids[] = parse_variable($lexer);
        if ($lexer->peek_token(TokenType::TOKEN_COMMA))
        {
            $lexer->eat_token();
        }
        else
        {
            break;
        }
    }

    $lexer->eat_token(TokenType::TOKEN_COLON);
    $type = parse_type($lexer);

    $declarations = [];
    foreach ($ids as $id)
    {
        $declarations[] = new VariableDeclaration($id, $type);
    }
    return $declarations;
}


function parse_procedure_declaration(Lexer $lexer): ProcedureDeclaration
{
    $lexer->eat_token(TokenType::TOKEN_PROCEDURE);
    $name = parse_variable($lexer);

    if ($lexer->peek_token(TokenType::TOKEN_LPARENS))
    {
        $lexer->eat_token();
        $parameters = parse_parameters($lexer);
        $lexer->eat_token(TokenType::TOKEN_RPARENS);
    }
    else
    {
        $parameters = [];
    }

    $lexer->eat_token(TokenType::TOKEN_SEMI);
    $body = parse_block($lexer);
    $lexer->eat_token(TokenType::TOKEN_SEMI);

    return new ProcedureDeclaration($name, $parameters, $body);
}


/**
 * @return VariableDeclaration[]
 */
function parse_parameters(Lexer $lexer): array
{
    $parameters = [];
    while (true)
    {
        foreach (parse_variable_declaration($lexer) as $parameter)
        {
            $parameters[] = $parameter;
        }
        if ($lexer->peek_token(TokenType::TOKEN_SEMI))
        {
            $lexer->eat_token();
        }
        else
        {
            break;
        }
    }
    return $parameters;
}


function parse_type(Lexer $lexer): Type
{
    $type = $lexer->eat_token(TokenType::TOKEN_INTEGER, TokenType::TOKEN_REAL);
    return new Type($type);
}


function parse_compound_statement(Lexer $lexer): CompoundStatement
{
    $lexer->eat_token(TokenType::TOKEN_BEGIN);
    $statements = parse_statements($lexer);
    $lexer->eat_token(TokenType::TOKEN_END);

    return new CompoundStatement($statements);
}


/**
 * @return Statement[]
 */
function parse_statements(Lexer $lexer): array
{
    $statements = [];
    while ($token = $lexer->peek_token(
        TokenType::TOKEN_BEGIN, TokenType::TOKEN_ID, TokenType::TOKEN_SEMI))
    {
        if (TokenType::TOKEN_BEGIN === $token->type())
        {
            $statements[] = parse_compound_statement($lexer);
        }
        elseif (TokenType::TOKEN_ID === $token->type())
        {
            $variable = parse_variable($lexer);
            $token = $lexer->eat_token(TokenType::TOKEN_ASSIGN,
                                       TokenType::TOKEN_LPARENS);
            if (TokenType::TOKEN_ASSIGN === $token->type())
            {
                $statements[] = parse_assignment($variable, $lexer);
            }
            else
            {
                $statements[] = parse_procedure_call($variable, $lexer);
            }
        }
        else
        {
            $lexer->eat_token();
        }
    }
    return $statements;
}


function parse_assignment(Variable $name, Lexer $lexer): Assignment
{
    $expression = parse_expression($lexer);
    return new Assignment($name, $expression);
}


function parse_procedure_call(Variable $name, Lexer $lexer): ProcedureCall
{
    $arguments = [];
    if (!$lexer->peek_token(TokenType::TOKEN_RPARENS))
    {
        while (true)
        {
            $arguments[] = parse_expression($lexer);
            if ($lexer->peek_token(TokenType::TOKEN_COMMA))
            {
                $lexer->eat_token();
            }
            else
            {
                break;
            }
        }
    }
    $lexer->eat_token(TokenType::TOKEN_RPARENS);
    return new ProcedureCall($name, $arguments);
}


function parse_expression(Lexer $lexer): Expression
{
    $result = parse_term($lexer);

    while ($lexer->peek_token(TokenType::TOKEN_PLUS, TokenType::TOKEN_MINUS))
    {
        $op = $lexer->eat_token();
        $right = parse_term($lexer);
        $result = new BinaryOperation($op, $result, $right);
    }

    return $result;
}


function parse_term(Lexer $lexer): Expression
{
    $result = parse_factor($lexer);

    while ($lexer->peek_token(TokenType::TOKEN_MUL, TokenType::TOKEN_DIV,
                              TokenType::TOKEN_INTDIV))
    {
        $op = $lexer->eat_token();
        $right = parse_factor($lexer);
        $result = new BinaryOperation($op, $result, $right);
    }

    return $result;
}


function parse_factor(Lexer $lexer): Expression
{
    $token = $lexer->peek_token(TokenType::TOKEN_INTEGER_LITERAL,
                                TokenType::TOKEN_FLOAT_LITERAL,
                                TokenType::TOKEN_LPARENS,
                                TokenType::TOKEN_PLUS,
                                TokenType::TOKEN_MINUS,
                                TokenType::TOKEN_ID);
    if (!$token)
    {
        $token = $lexer->eat_token();
        unexpected_token($token);
    }

    $type = $token->type();
    if (TokenType::TOKEN_INTEGER_LITERAL === $type
        || TokenType::TOKEN_FLOAT_LITERAL === $type)
    {
        return new Number($lexer->eat_token());
    }
    elseif (TokenType::TOKEN_LPARENS === $type)
    {
        $lexer->eat_token();
        $result = parse_expression($lexer);
        $lexer->eat_token(TokenType::TOKEN_RPARENS);
        return $result;
    }
    elseif (TokenType::TOKEN_ID === $type)
    {
        return parse_variable($lexer);
    }
    else
    {
        $operation = $lexer->eat_token();
        $expression = parse_factor($lexer);
        return new UnaryOperation($operation, $expression);
    }
}


function parse_variable(Lexer $lexer): Variable
{
    $variable = $lexer->eat_token(TokenType::TOKEN_ID);
    return new Variable($variable);
}
