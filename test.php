<?php

use easytest\Context;
use function easytest\assert_identical;
use function easytest\assert_throws;

use interpreter\NameError;
use interpreter\ParseError;
use function interpreter\run_code;


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
        $actual = [];
        run_code($code, $actual);

        $c->assert_identical($expected, $actual['a']);
    }
}


function test_invalid_syntax(Context $c)
{
    $statements = [
        ['10 *', "Invalid term: Token(SEMI, ';')"],
        ['1 (1 + 2)', "Expected token END but got token Token(LPARENS, '(')"]
    ];

    foreach ($statements as [$statement, $error])
    {
        $state = [];
        $c->assert_throws(
            ParseError::class,
            function() use ($statement, &$state) {
                $code = <<<CODE
PROGRAM Test;
VAR
    a : INTEGER;
BEGIN
    a := {$statement};
END.
CODE;
                run_code($code, $state);
            },
            null,
            $result
        );
        $c->assert_identical($error, $result->getMessage());
        $c->assert_identical([], $state, 'resulting state');
    }
}


function test_program()
{
    $code = <<<'CODE'
PROGRAM TestProgram;
VAR
    number      : INTEGER;
    a, b, c, x  : INTEGER;
    y           : REAL;

PROCEDURE P1;
VAR
   a : REAL;
   k : INTEGER;
   PROCEDURE P2;
   VAR
      a, z : INTEGER;
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

    $actual = [];
    run_code($code, $actual);

    assert_identical($expected, $actual);
}


function test_case_sensitivity()
{
    $code = <<<'CODE'
PROGRAM TestCaseSensitivity;
VAR
    number      : INTEGER;
    a, b, c, x  : INTEGER;
    _half_x     : INTEGER;
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

    $actual = [];
    run_code($code, $actual);

    assert_identical($expected, $actual);
}


function test_undefined_variable_reference()
{
    $actual = assert_throws(
        NameError::class,
        function() {
            $code = <<<'CODE'
PROGRAM NameError1;
VAR
   a : INTEGER;
BEGIN
   a := 2 + b;
END.
CODE;
            $state = [];
            run_code($code, $state);
        }
    );
    assert_identical("Undeclared name: b", $actual->getMessage());
}


function test_undefined_variable_assignment()
{
    $actual = assert_throws(
        NameError::class,
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
            $state = [];
            run_code($code, $state);
        }
    );
    assert_identical("Undeclared name: a", $actual->getMessage());
}


function test_redefined_variable()
{
    $actual = assert_throws(
        NameError::class,
        function() {
            $code = <<<'CODE'
program SymTab6;
var
    x, y : integer;
    y    : real;
begin
   x := x + y;
end.
CODE;
            $state = [];
            run_code($code, $state);
        }
    );
    assert_identical("Redefinition of name: y", $actual->getMessage());
}


function test_scope()
{
    $code = <<<'CODE'
program Main;
   var b, x, y : real;
   z : integer;

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
    $state = [];
    run_code($code, $state);
}
