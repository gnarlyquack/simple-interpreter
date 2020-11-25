<?php declare(strict_types=1);

namespace interpreter;


final class Lexer
{
    const KEYWORDS = [
        'BEGIN' => TokenType::TOKEN_BEGIN,
        'END' => TokenType::TOKEN_END,
        'DIV' => TokenType::TOKEN_INTDIV,
        'INTEGER' => TokenType::TOKEN_INTEGER,
        'REAL' => TokenType::TOKEN_REAL,
        'VAR' => TokenType::TOKEN_VAR,
        'PROGRAM' => TokenType::TOKEN_PROGRAM,
        'PROCEDURE' => TokenType::TOKEN_PROCEDURE,
    ];

    private string $input;
    private int $len;
    private int $pos;
    private int $line;
    private int $col;
    private Token $current_token;
    /** @var Token[] */
    private array $keywords;

    public function __construct(string $input)
    {

        $this->input = $input;
        $this->len = \strlen($input);
        $this->pos = 0;
        $this->line = $this->col = 1;
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

        unexpected_token($result);
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
            return $this->token(TokenType::TOKEN_EOF, $char);
        }

        if (\ctype_digit($char))
        {
            $char .= $this->eat_chars('ctype_digit');
            if ('.' === $this->peek_char())
            {
                $char .= $this->eat_char();
                $char .= $this->eat_chars('ctype_digit');
                return $this->token(TokenType::TOKEN_FLOAT_LITERAL, (float)$char);
            }
            else
            {
                return $this->token(TokenType::TOKEN_INTEGER_LITERAL, (int)$char);
            }
        }

        if (\preg_match('~^[_a-zA-Z]$~', $char))
        {
            $char .= $this->eat_pattern('~\\G[_a-zA-Z0-9]+~');
            $keyword = \strtoupper($char);
            if (isset(self::KEYWORDS[$keyword]))
            {
                return $this->token(self::KEYWORDS[$keyword], $keyword);
            }
            return $this->token(TokenType::TOKEN_ID, \strtolower($char));
        }

        if ('+' === $char)
        {
            return $this->token(TokenType::TOKEN_PLUS, $char);
        }
        if ('-' === $char)
        {
            return $this->token(TokenType::TOKEN_MINUS, $char);
        }
        if ('*' === $char)
        {
            return $this->token(TokenType::TOKEN_MUL, $char);
        }
        if ('/' === $char)
        {
            return $this->token(TokenType::TOKEN_DIV, $char);
        }
        if ('(' === $char)
        {
            return $this->token(TokenType::TOKEN_LPARENS, $char);
        }
        if (')' === $char)
        {
            return $this->token(TokenType::TOKEN_RPARENS, $char);
        }
        if ('.' === $char)
        {
            return $this->token(TokenType::TOKEN_DOT, $char);
        }
        if (';' === $char)
        {
            return $this->token(TokenType::TOKEN_SEMI, $char);
        }
        if (':' === $char)
        {
            if ('=' === $this->peek_char())
            {
                $char .= $this->eat_char();
                return $this->token(TokenType::TOKEN_ASSIGN, $char);
            }
            else
            {
                return $this->token(TokenType::TOKEN_COLON, $char);
            }
        }
        if (',' === $char)
        {
            return $this->token(TokenType::TOKEN_COMMA, $char);
        }

        unknown_token($char, $this->line, $this->col);
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
            ++$this->col;
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
            $char = $this->input[$this->pos++];
            $result .= $char;
            if ("\n" === $char)
            {
                ++$this->line;
                $this->col = 1;
            }
            else
            {
                ++$this->col;
            }
        }
        return $result;
    }


    private function eat_pattern(string $pattern): string
    {
        \assert(false === \strpos($pattern, "\n"));

        if ($this->pos < $this->len
            && \preg_match($pattern, $this->input, $matches, 0, $this->pos))
        {
            $result = $matches[0];
            $len = \strlen($result);
            $this->pos += $len;
            $this->col += $len;
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
        $comment_line = $this->line;
        $comment_col = $this->col;

        while ($this->pos < $this->len)
        {
            $char = $this->input[$this->pos];
            if ($comment || \ctype_space($char) || '{' === $char)
            {
                ++$this->pos;
                if ("\n" === $char)
                {
                    ++$this->line;
                    $this->col = 1;
                }
                else
                {
                    ++$this->col;
                }

                if ('{' === $char)
                {
                    $comment = true;
                    $comment_line = $this->line;
                    $comment_col = $this->col;
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
            unclosed_comment($comment_line, $comment_col);
        }
    }


    /**
     * @param TokenType::TOKEN_* $type;
     * @param mixed $value;
     */
    private function token(int $type, $value): Token
    {
        return new Token($type, $value, $this->line, $this->col);
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
    const TOKEN_PROCEDURE = 22;

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
        self::TOKEN_PROCEDURE => 'PROCEDURE',
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
    private int $line;
    private int $col;

    /**
     * @param TokenType::TOKEN_* $type
     * @param mixed $value
     */
    public function __construct(int $type, $value, int $line, int $col)
    {
        $this->type = $type;
        $this->value = $value;
        $this->line = $line;
        $this->col = $col;
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


    public function line(): int
    {
        return $this->line;
    }


    public function col(): int
    {
        return $this->col;
    }


    public function __toString(): string
    {
        return \sprintf(
            'Token(%s, %s)',
            TokenType::name($this->type), \var_export($this->value, true)
        );
    }
}
