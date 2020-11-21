<?php declare(strict_types=1);

namespace interpreter;


abstract class Ast {}


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
                    TokenType::TOKEN_DIV, TokenType::TOKEN_MUL
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

    while ($lexer->peek_token(TokenType::TOKEN_MUL, TokenType::TOKEN_DIV))
    {
        $op = $lexer->eat_token();
        $right = parse_factor($lexer);
        $result = new BinaryOperation($op, $result, $right);
    }

    return $result;
}


function parse_factor(Lexer $lexer): Expression
{
    $result = $lexer->eat_token(
        TokenType::TOKEN_NUMBER, TokenType::TOKEN_LPARENS,
        TokenType::TOKEN_PLUS, TokenType::TOKEN_MINUS);
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
    else
    {
        return new UnaryOperation($result, parse_factor($lexer));
    }
}
