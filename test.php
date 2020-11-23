<?php

use easytest\Context;
use function easytest\assert_identical;
use function easytest\assert_throws;

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
    foreach ($tests as [$expr, $expected])
    {
        $actual = [];
        run_code("BEGIN a := {$expr} END.", $actual);

        $c->assert_identical($expected, $actual['a']);
    }
}


function test_invalid_syntax(Context $c)
{
    $statements = ['10 *', '1 (1 + 2)'];

    foreach ($statements as $statement)
    {
        $state = [];
        $c->assert_throws(
            ParseError::class,
            function() use ($statement, &$state) {
                run_code("BEGIN a := {$statement} END.", $state);
            }
        );
        $c->assert_identical([], $state, 'resulting state');
    }
}


function test_statements()
{
    $code = <<<'CODE'
BEGIN
    BEGIN
        number := 2;
        a := number;
        b := 10 * a + 10 * number / 4;
        c := a - - b
    END;
    x := 11;
END.
CODE;

    $expected = [
        'number' => 2,
        'a' => 2,
        'b' => 25,
        'c' => 27,
        'x' => 11,
    ];

    $actual = [];
    run_code($code, $actual);

    assert_identical($expected, $actual);
}


function test_case_sensitivity()
{
    $code = <<<'CODE'
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
