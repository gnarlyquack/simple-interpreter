<?php

use easytest\Context;
use function easytest\assert_identical;
use function easytest\assert_throws;

use interpreter\ParseError;
use interpreter\SemanticError;
use interpreter\Lexer;
use interpreter\Memory;
use function interpreter\check_program;
use function interpreter\interpret_program;
use function interpreter\parse_program;


function run_code(string $code): Memory
{
    $program = parse_program(new Lexer($code));
    check_program($program);

    $state = new Memory;
    interpret_program($program, $state);
    return $state;
}


function test_arithmetic_expressions(Context $c)
{
    $tests = [
        ['3', 3],
        ['2 + 7 * 4', 30],
        ['7 - 8 / 4', 5],
        ['14 + 2 * 3 - 6 / 2', 17],
        ['7 + 3 * (10 / (12 / (3 + 1) - 1))', 22],
        ['7 + 3 * (10 / (12 / (3 + 1) - 1)) / (2 + 3) - 5 - 3 + (8)', 10],
        ['7 + (((3 + 2)))', 12],
        ['- 3', -3],
        ['+ 3', 3],
        ['5 - - - + - 3', 8],
        ['5 - - - + - (3 + 4) - +2', 10],
    ];
    foreach ($tests as [$expression, $expected])
    {
        $code = <<<CODE
PROGRAM Test;
VAR
    a : INTEGER;
BEGIN
    a := {$expression}
END.
CODE;

        $actual = run_code($code)->peek_frame()->variables();
        $c->assert_identical(['a' => $expected], $actual);
    }
}


function test_invalid_syntax(Context $c)
{
    $statements = [
        ['10 *', "Unexpected token: Token(SEMI, ';')"],
        ['1 (1 + 2)', "Unexpected token: Token(LPARENS, '(')"]
    ];

    foreach ($statements as [$statement, $error])
    {
        $c->assert_throws(
            ParseError::class,
            function() use ($statement) {
                $code = <<<CODE
PROGRAM Test;
VAR
    a : INTEGER;
BEGIN
    a := {$statement};
END.
CODE;
                run_code($code)->peek_frame()->variables();
            },
            null,
            $result
        );
        $c->assert_identical($error, $result->getMessage());
    }
}


function test_program()
{
    $code = <<<'CODE'
PROGRAM TestProgram;
VAR number      : INTEGER;
VAR a, b, c, x  : INTEGER;
VAR y           : REAL;

PROCEDURE P1;
VAR a : REAL;
VAR k : INTEGER;
   PROCEDURE P2;
   VAR a, z : INTEGER;
   BEGIN {P2}
      z := 777;
   END;  {P2}
BEGIN {P1}
END;  {P1}

BEGIN {TestProgram}
    BEGIN
        number := 2;
        a := number;
        b := 10 * a + 10 * number DIV 4;
        c := a - - b
    END;
    x := 11;
    y := 20 / 7 + 3.14
END. {TestProgram}
CODE;

    $expected = [
        'number' => 2,
        'a' => 2,
        'b' => 25,
        'c' => 27,
        'x' => 11,
        'y' => 20 / 7 + 3.14,
    ];

    $actual = run_code($code)->peek_frame()->variables();
    assert_identical($expected, $actual);
}


function test_case_sensitivity()
{
    $code = <<<'CODE'
PROGRAM TestCaseSensitivity;
VAR number      : INTEGER;
VAR a, b, c, x  : INTEGER;
VAR _half_x     : INTEGER;
BEGIN

    BEGIN
        number := 2;
        a := NumBer;
        B := 10 * a + 10 * NUMBER / 4;
        c := a - - b
    end;

    x := 11;
    _half_x := x div 2
END.
CODE;

    $expected = [
        'number' => 2,
        'a' => 2,
        'b' => 25,
        'c' => 27,
        'x' => 11,
        '_half_x' => 5,
    ];

    $actual = run_code($code)->peek_frame()->variables();
    assert_identical($expected, $actual);
}


function test_undefined_variable_reference()
{
    $actual = assert_throws(
        SemanticError::class,
        function() {
            $code = <<<'CODE'
PROGRAM NameError1;
VAR
   a : INTEGER;
BEGIN
   a := 2 + b;
END.
CODE;
            run_code($code);
        }
    );
    assert_identical(
        "Undeclared identifier: Token(ID, 'b')",
        $actual->getMessage()
    );
}


function test_undefined_variable_assignment()
{
    $actual = assert_throws(
        SemanticError::class,
        function() {
            $code = <<<'CODE'
PROGRAM NameError2;
VAR
   b : INTEGER;
BEGIN
   b := 1;
   a := b + 2;
END.
CODE;
            run_code($code);
        }
    );
    assert_identical(
        "Undeclared identifier: Token(ID, 'a')",
        $actual->getMessage()
    );
}


function test_redefined_variable()
{
    $actual = assert_throws(
        SemanticError::class,
        function() {
            $code = <<<'CODE'
program SymTab6;
    var x, y : integer;
    var y    : real;
begin
   x := x + y;
end.
CODE;
            run_code($code);
        }
    );
    assert_identical(
        "Redefinition of identifier: Token(ID, 'y')",
        $actual->getMessage()
    );
}


function test_scope()
{
    $code = <<<'CODE'
program Main;
   var b, x, y : real;
   var z : integer;

   procedure AlphaA(a : integer);
      var b : integer;

      procedure Beta(c : integer);
         var y : integer;

         procedure Gamma(c : integer);
            var x : integer;
         begin { Gamma }
            x := a + b + c + x + y + z;
         end;  { Gamma }

      begin { Beta }

      end;  { Beta }

   begin { AlphaA }

   end;  { AlphaA }

   procedure AlphaB(a : integer);
      var c : real;
   begin { AlphaB }
      c := a + b;
   end;  { AlphaB }

begin { Main }
end.  { Main }
CODE;
    run_code($code);
}


function test_procedure_call()
{
    $code = <<<'CODE'
program Main;
procedure Alpha(a : integer; b : integer);
var x : integer;
begin
   x := (a + b ) * 2;
end;
begin { Main }
   Alpha(3 + 5, 7);
end.  { Main }
CODE;

    $actual = run_code($code)->peek_frame()->variables();
    /*
    $expected = ['x' => 30];
    assert_identical($expected, $actual);
     */
}


function test_argument_mismatch()
{
    $tests = [
        ['alpha();', "Procedure 'alpha' called with 0 arguments but takes 2"],
        ['alpha(1);', "Procedure 'alpha' called with 1 arguments but takes 2"],
        ['alpha(1, 2, 3);', "Procedure 'alpha' called with 3 arguments but takes 2"],
    ];
    $template = <<<'CODE'
program Main;
procedure Alpha(a : integer; b : integer);
var x : integer;
begin
   x := (a + b ) * 2;
end;
begin { Main }
    %s
end.  { Main }
CODE;
    foreach ($tests as [$test, $expected])
    {
        $code = \sprintf($template, $test);
        $actual = assert_throws(
            SemanticError::class,
            function() use ($code) {
                run_code($code);
            }
        );
        assert_identical($expected, $actual->getMessage());
    }
}


function test_nested_procedures()
{
    $code = <<<'CODE'
program Main;

procedure Alpha(a : integer; b : integer);
var x : integer;

   procedure Beta(a : integer; b : integer);
   var x : integer;
   begin
      x := a * 10 + b * 2;
   end;

begin
   x := (a + b ) * 2;

   Beta(5, 10);      { procedure call }
end;

begin { Main }

   Alpha(3 + 5, 7);  { procedure call }

   end.  { Main }
CODE;

    $actual = run_code($code)->peek_frame()->variables();
    /*
    $expected = ['x' => 30];
    assert_identical($expected, $actual);
     */
}
