<?php declare(strict_types=1);

namespace interpreter;


main();


final class InvalidCodePath extends \Exception {}
final class ParseError extends \Exception {}


final class Lexer
{
    private string $input;
    private int $len;
    private int $pos;
    private Token $current_token;

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->len = \strlen($input);
        $this->pos = 0;
        $this->current_token = $this->next_token();
    }


    public function has_token(): bool
    {
        return $this->pos < $this->len;
    }

    /**
     * @param TokenType::TOKEN_*... $types
     */
    public function eat_token(int ...$types): Token
    {
        $result = $this->current_token;
        if (!$types || \in_array($result->type(), $types))
        {
            $this->current_token = $this->next_token();
            return $result;
        }

        $expected = \implode(
            ' or ',
            \array_map([TokenType::class, 'name'], $types));
        throw new ParseError("Expected token {$expected} but got token {$result}");
    }

    /**
     * @param TokenType::TOKEN_*... $types
     */
    public function peek_token(int ...$types): ?Token
    {
        $result = $this->current_token;
        return \in_array($result->type(), $types) ? $result : null;
    }


    private function next_token(): Token
    {
        $this->eat_chars('ctype_space');

        if ($this->pos >= $this->len)
        {
            return new Token(TokenType::TOKEN_EOF);
        }

        $char = $this->input[$this->pos++];

        if (\ctype_digit($char))
        {
            $char .= $this->eat_chars('ctype_digit');
            return new Token(TokenType::TOKEN_NUMBER, (int)$char);
        }

        if ('+' === $char)
        {
            return new Token(TokenType::TOKEN_PLUS, $char);
        }
        if ('-' === $char)
        {
            return new Token(TokenType::TOKEN_MINUS, $char);
        }
        if ('*' === $char)
        {
            return new Token(TokenType::TOKEN_MUL, $char);
        }
        if ('/' === $char)
        {
            return new Token(TokenType::TOKEN_DIV, $char);
        }
        if ('(' === $char)
        {
            return new Token(TokenType::TOKEN_LPARENS, $char);
        }
        if (')' === $char)
        {
            return new Token(TokenType::TOKEN_RPARENS, $char);
        }

        throw new ParseError("Unknown token: {$char}");
    }

    /**
     * @param callable(string): bool $predicate
     */
    private function eat_chars(callable $predicate): string
    {
        $result = '';
        while ($this->pos < $this->len && $predicate($this->input[$this->pos]))
        {
            $result .= $this->input[$this->pos++];
        }
        return $result;
    }
}


final class TokenType
{
    const TOKEN_EOF = 0;
    const TOKEN_NUMBER = 1;
    const TOKEN_PLUS = 2;
    const TOKEN_MINUS = 3;
    const TOKEN_DIV = 4;
    const TOKEN_MUL = 5;
    const TOKEN_LPARENS = 6;
    const TOKEN_RPARENS = 7;

    const NAME = [
        self::TOKEN_EOF => 'EOF',
        self::TOKEN_NUMBER => 'NUMBER',
        self::TOKEN_PLUS => 'PLUS',
        self::TOKEN_MINUS => 'MINUS',
        self::TOKEN_DIV => 'DIV',
        self::TOKEN_MUL => 'MUL',
        self::TOKEN_LPARENS => 'LPARENS',
        self::TOKEN_RPARENS => 'RPARENS',
    ];

    /*
     * @param self::* $type
     */
    public static function name(int $type): string
    {
        return self::NAME[$type];
    }

    private function __construct() {}
}


final class Token
{
    /** @var TokenType::TOKEN_* */
    private int $type;
    /** @var mixed */
    private $value;

    /**
     * @param TokenType::TOKEN_* $type
     * @param mixed $value
     */
    public function __construct(int $type, $value=null)
    {
        $this->type = $type;
        $this->value = $value;
    }

    /**
     * @return TokenType::TOKEN_*
     */
    public function type(): int
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }


    public function __toString(): string
    {
        return \sprintf(
            'Token(%s, %s)',
            TokenType::name($this->type), \var_export($this->value, true)
        );
    }
}



function main(): void
{
    while (true)
    {
        $input = \readline('calc> ');
        if ($input)
        {
            $result = parse_expression(new Lexer($input));
            echo "{$result}\n";
        }
    }
}


/**
 * @return int|float
 */
function parse_expression(Lexer $lexer)
{
    $result = parse_term($lexer);

    while ($lexer->peek_token(TokenType::TOKEN_PLUS, TokenType::TOKEN_MINUS))
    {
        $op = $lexer->eat_token();
        $right = parse_term($lexer);

        if (TokenType::TOKEN_PLUS === $op->type())
        {
            $result += $right;
        }
        elseif (TokenType::TOKEN_MINUS === $op->type())
        {
            $result -= $right;
        }
        else
        {
            throw new InvalidCodePath("Unexpected token {$op}");
        }
    }

    return $result;
}


/**
 * @return int|float
 */
function parse_term(Lexer $lexer)
{
    $result = parse_factor($lexer);

    while ($lexer->peek_token(TokenType::TOKEN_MUL, TokenType::TOKEN_DIV))
    {
        $op = $lexer->eat_token();
        $right = parse_factor($lexer);

        if (TokenType::TOKEN_DIV === $op->type())
        {
            $result /= $right;
        }
        elseif (TokenType::TOKEN_MUL === $op->type())
        {
            $result *= $right;
        }
        else
        {
            throw new InvalidCodePath("Unexpected token {$op}");
        }
    }

    return $result;
}


/**
 * @return int|float
 */
function parse_factor(Lexer $lexer)
{
    $result = $lexer->eat_token(TokenType::TOKEN_NUMBER, TokenType::TOKEN_LPARENS);
    if (TokenType::TOKEN_NUMBER === $result->type())
    {
        return $result->value();
    }
    else
    {
        $result = parse_expression($lexer);
        $lexer->eat_token(TokenType::TOKEN_RPARENS);
        return $result;
    }
}
