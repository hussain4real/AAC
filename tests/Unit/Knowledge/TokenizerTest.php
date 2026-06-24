<?php

use App\Support\Runtime\Knowledge\Tokenizer;

test('tokenize lowercases, splits on non-alphanumeric, and keeps repeats', function () {
    expect(Tokenizer::tokenize('Berth allocation, BERTH policy!'))
        ->toBe(['berth', 'allocation', 'berth', 'policy']);
});

test('tokenize drops one-character tokens and stopwords', function () {
    expect(Tokenizer::tokenize('the vessel is a ship'))
        ->toBe(['vessel', 'ship']);
});

test('tokenize keeps numbers and handles unicode boundaries', function () {
    expect(Tokenizer::tokenize('Port-42 réception'))
        ->toBe(['port', '42', 'réception']);
});

test('terms reduces to distinct tokens preserving order', function () {
    expect(Tokenizer::terms('berth berth allocation berth'))
        ->toBe(['berth', 'allocation']);
});

test('tokenize returns an empty list for empty or stopword-only text', function () {
    expect(Tokenizer::tokenize(''))->toBe([])
        ->and(Tokenizer::tokenize('the a an of'))->toBe([]);
});
