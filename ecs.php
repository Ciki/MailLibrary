<?php

declare(strict_types=1);

use Ecs\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

require_once __DIR__ . '/libs/Ecs/Fixer/ClassNotation/ClassAttributesSeparationFixer.php';

return static function (ECSConfig $ecsConfig): void {
	$ecsConfig->parallel();

	$ecsConfig->paths([
		__DIR__ . '/MailLibrary',
		__DIR__ . '/tests',
	]);

	$ecsConfig->indentation('tab');

	$ecsConfig->skip([
		\PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer::class, // disable the original, use our customized version
	]);

	$ecsConfig->sets([
		SetList::PSR_12,
		SetList::STRICT,
		SetList::CLEAN_CODE,
	]);

	// override default from SetList so that this fixer does NOT remove extra blank lines between code blocks added by custom ClassAttributesSeparationFixer
	$ecsConfig->ruleWithConfiguration(\PhpCsFixer\Fixer\Whitespace\NoExtraBlankLinesFixer::class, [
		'tokens' => ['continue', 'default', 'return', 'switch', 'use', 'use_trait'],
	]);

	$ecsConfig->ruleWithConfiguration(ClassAttributesSeparationFixer::class, [
		'elements' => [
			'const' => 'only_if_meta',
			'trait_import' => 'only_if_meta',
			'property' => ClassAttributesSeparationFixer::SPACING_ONE,
			'method' => ClassAttributesSeparationFixer::SPACING_TWO,
		],
	]);
};
