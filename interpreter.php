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

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->len = \strlen($input);
        $this->pos = 0;
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
        $result = $this->next_token();
        if (\in_array($result->type(), $types))
        {
            return $result;
        }

        $expected = \implode(
            ' or ',
            \array_map([TokenType::class, 'name'], $types));
        parse_error("Expected token {$expected} but got token {$result})");
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

        parse_error("Unknown token: {$char}");
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

    const NAME = [
        self::TOKEN_EOF => 'EOF',
        self::TOKEN_NUMBER => 'NUMBER',
        self::TOKEN_PLUS => 'PLUS',
        self::TOKEN_MINUS => 'MINUS',
        self::TOKEN_DIV => 'DIV',
        self::TOKEN_MUL => 'MUL',
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
 * @return mixed
 */
function parse_expression(Lexer $lexer)
{
    $result = $lexer->eat_token(TokenType::TOKEN_NUMBER)->value();

    while ($lexer->has_token())
    {
        $op = $lexer->eat_token(TokenType::TOKEN_PLUS, TokenType::TOKEN_MINUS,
                                TokenType::TOKEN_MUL, TokenType::TOKEN_DIV);
        $right = $lexer->eat_token(TokenType::TOKEN_NUMBER)->value();

        if (TokenType::TOKEN_PLUS === $op->type())
        {
            $result += $right;
        }
        elseif (TokenType::TOKEN_MINUS === $op->type())
        {
            $result -= $right;
        }
        elseif (TokenType::TOKEN_DIV === $op->type())
        {
            $result /= $right;
        }
        elseif (TokenType::TOKEN_MUL === $op->type())
        {
            $result *= $right;
        }
        else
        {
            invalid_code_path("Unexpected operation {$op}");
        }
    }

    return $result;
}


/**
 * @return never
 */
function parse_error(string $message): void
{
    throw new ParseError($message);
}


/**
 * @return never
 */
function invalid_code_path(string $message=''): void
{
    throw new InvalidCodePath($message);
}
