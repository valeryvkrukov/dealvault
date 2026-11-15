<?php

namespace DealVault\Shared\Doctrine\Query;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Implementation of native PostgreSQL 'jsonb_column ->> field' operator for Doctrine DQL.
 * Syntax in DQL: JSONB_GET_TEXT(d.customValues, 'sector')
 */
class JsonbGetText extends FunctionNode
{
    private ?Node $jsonDataExpression = null;
    private ?Node $jsonKeyExpression = null;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return '(' .
            $this->jsonDataExpression->dispatch($sqlWalker) . ' ->> ' .
            $this->jsonKeyExpression->dispatch($sqlWalker) .
        ')';
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->jsonDataExpression = $parser->StringPrimary();

        $parser->match(TokenType::T_COMMA);

        $this->jsonKeyExpression = $parser->StringPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
