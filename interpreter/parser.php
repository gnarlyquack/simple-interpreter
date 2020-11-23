<?php declare(strict_types=1);

namespace interpreter;


abstract class Ast {}


abstract class Statement extends Ast {}


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


    public function variable(): string
    {
        return $this->variable->identifier();
    }


    public function expression(): Expression
    {
        return $this->expression;
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
        \assert(TokenType::TOKEN_NUMBER === $number->type());
        $this->number = $number;
    }


    public function value(): int
    {
        return $this->number->value();
    }
}


final class Variable extends Expression
{
    private Token $identifier;

    public function __construct(Token $identifier)
    {
        \assert(TokenType::TOKEN_ID === $identifier->type());
        $this->identifier = $identifier;
    }


    public function identifier(): string
    {
        return $this->identifier->value();
    }
}



function parse_program(Lexer $lexer): CompoundStatement
{
    $statements = parse_statements($lexer);
    $lexer->eat_token(TokenType::TOKEN_DOT);
    return $statements;
}


function parse_statements(Lexer $lexer): CompoundStatement
{
    $lexer->eat_token(TokenType::TOKEN_BEGIN);

    $statements = [];
    while ($token = $lexer->peek_token(
        TokenType::TOKEN_BEGIN, TokenType::TOKEN_ID, TokenType::TOKEN_SEMI))
    {
        if (TokenType::TOKEN_BEGIN === $token->type())
        {
            $statements[] = parse_statements($lexer);
        }
        elseif (TokenType::TOKEN_ID === $token->type())
        {
            $statements[] = parse_assignment($lexer);
        }
        else
        {
            $lexer->eat_token();
        }
    }
    $lexer->eat_token(TokenType::TOKEN_END);

    return new CompoundStatement($statements);
}


function parse_assignment(Lexer $lexer): Assignment
{
    $variable = new Variable($lexer->eat_token(TokenType::TOKEN_ID));
    $lexer->eat_token(TokenType::TOKEN_ASSIGN);
    $expression = parse_expression($lexer);
    return new Assignment($variable, $expression);
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
    $result = $lexer->eat_token(TokenType::TOKEN_NUMBER,
                                TokenType::TOKEN_LPARENS,
                                TokenType::TOKEN_PLUS,
                                TokenType::TOKEN_MINUS,
                                TokenType::TOKEN_ID);
    if (TokenType::TOKEN_NUMBER === $result->type())
    {
        return new Number($result);
    }
    elseif (TokenType::TOKEN_LPARENS === $result->type())
    {
        $result = parse_expression($lexer);
        $lexer->eat_token(TokenType::TOKEN_RPARENS);
        return $result;
    }
    elseif (TokenType::TOKEN_ID === $result->type())
    {
        return new Variable($result);
    }
    else
    {
        return new UnaryOperation($result, parse_factor($lexer));
    }
}
