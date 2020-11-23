<?php declare(strict_types=1);

namespace interpreter;


final class Lexer
{
    private string $input;
    private int $len;
    private int $pos;
    private Token $current_token;
    /** @var Token[] */
    private array $keywords;

    public function __construct(string $input)
    {
        $this->keywords = [
            'BEGIN' => new Token(TokenType::TOKEN_BEGIN, 'BEGIN'),
            'END' => new Token(TokenType::TOKEN_END, 'END'),
            'DIV' => new Token(TokenType::TOKEN_INTDIV, 'DIV'),
            'INTEGER' => new Token(TokenType::TOKEN_INTEGER, 'INTEGER'),
            'REAL' => new Token(TokenType::TOKEN_REAL, 'REAL'),
            'VAR' => new Token(TokenType::TOKEN_VAR, 'VAR'),
            'PROGRAM' => new Token(TokenType::TOKEN_PROGRAM, 'PROGRAM'),
        ];

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
        $this->eat_whitespace();
        $char = $this->eat_char();

        if ('' === $char)
        {
            return new Token(TokenType::TOKEN_EOF);
        }

        if (\ctype_digit($char))
        {
            $char .= $this->eat_chars('ctype_digit');
            if ('.' === $this->peek_char())
            {
                $char .= $this->eat_char();
                $char .= $this->eat_chars('ctype_digit');
                return new Token(TokenType::TOKEN_FLOAT_LITERAL, (float)$char);
            }
            else
            {
                return new Token(TokenType::TOKEN_INTEGER_LITERAL, (int)$char);
            }
        }

        if (\preg_match('~^[_a-zA-Z]$~', $char))
        {
            $char .= $this->eat_pattern('~\\G[_a-zA-Z0-9]+~');
            $keyword = \strtoupper($char);
            if (isset($this->keywords[$keyword]))
            {
                return $this->keywords[$keyword];
            }
            return new Token(TokenType::TOKEN_ID, \strtolower($char));
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
        if ('.' === $char)
        {
            return new Token(TokenType::TOKEN_DOT, $char);
        }
        if (';' === $char)
        {
            return new Token(TokenType::TOKEN_SEMI, $char);
        }
        if (':' === $char)
        {
            if ('=' === $this->peek_char())
            {
                $char .= $this->eat_char();
                return new Token(TokenType::TOKEN_ASSIGN, $char);
            }
            else
            {
                return new Token(TokenType::TOKEN_COLON, $char);
            }
        }
        if (',' === $char)
        {
            return new Token(TokenType::TOKEN_COMMA, $char);
        }

        throw new ParseError("Unknown token: {$char}");
    }


    private function peek_char(): string
    {
        if ($this->pos < $this->len)
        {
            return $this->input[$this->pos];
        }
        return '';
    }


    private function eat_char(): string
    {
        if ($this->pos < $this->len)
        {
            return $this->input[$this->pos++];
        }
        return '';
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


    private function eat_pattern(string $pattern): string
    {
        if ($this->pos < $this->len
            && \preg_match($pattern, $this->input, $matches, 0, $this->pos))
        {
            $result = $matches[0];
            $this->pos += \strlen($result);
        }
        else
        {
            $result = '';
        }
        return $result;
    }


    private function eat_whitespace(): void
    {
        $comment = false;
        while ($this->pos < $this->len)
        {
            $char = $this->input[$this->pos];
            if ($comment || \ctype_space($char) || '{' === $char)
            {
                ++$this->pos;
                if ('{' === $char)
                {
                    $comment = true;
                }
                elseif ('}' === $char)
                {
                    $comment = false;
                }
            }
            else
            {
                break;
            }
        }
        if ($comment)
        {
            throw new ParseError('Unclosed comment');
        }
    }
}


final class TokenType
{
    const TOKEN_EOF = 0;
    const TOKEN_INTEGER_LITERAL = 1;
    const TOKEN_PLUS = 2;
    const TOKEN_MINUS = 3;
    const TOKEN_DIV = 4;
    const TOKEN_MUL = 5;
    const TOKEN_LPARENS = 6;
    const TOKEN_RPARENS = 7;
    const TOKEN_DOT = 8;
    const TOKEN_ASSIGN = 9;
    const TOKEN_ID = 10;
    const TOKEN_BEGIN = 11;
    const TOKEN_END = 12;
    const TOKEN_SEMI = 13;
    const TOKEN_INTDIV = 14;
    const TOKEN_FLOAT_LITERAL = 15;
    const TOKEN_INTEGER = 16;
    const TOKEN_REAL = 17;
    const TOKEN_COMMA = 18;
    const TOKEN_COLON = 19;
    const TOKEN_VAR = 20;
    const TOKEN_PROGRAM = 21;

    const NAME = [
        self::TOKEN_EOF => 'EOF',
        self::TOKEN_INTEGER_LITERAL => 'INTEGER LITERAL',
        self::TOKEN_PLUS => 'PLUS',
        self::TOKEN_MINUS => 'MINUS',
        self::TOKEN_DIV => 'DIV',
        self::TOKEN_MUL => 'MUL',
        self::TOKEN_LPARENS => 'LPARENS',
        self::TOKEN_RPARENS => 'RPARENS',
        self::TOKEN_DOT => 'DOT',
        self::TOKEN_ASSIGN => 'ASSIGN',
        self::TOKEN_ID => 'ID',
        self::TOKEN_BEGIN => 'BEGIN',
        self::TOKEN_END => 'END',
        self::TOKEN_SEMI => 'SEMI',
        self::TOKEN_INTDIV => 'INTDIV',
        self::TOKEN_FLOAT_LITERAL => 'FLOAT LITERAL',
        self::TOKEN_INTEGER => 'INTEGER',
        self::TOKEN_REAL => 'REAL',
        self::TOKEN_COMMA => 'COMMA',
        self::TOKEN_COLON => 'COLON',
        self::TOKEN_VAR => 'VAR',
        self::TOKEN_PROGRAM => 'PROGRAM',
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
