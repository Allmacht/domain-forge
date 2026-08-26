<?php

namespace Yntech\DomainForge\Support;

final readonly class Property
{
	/**
	 * @param array<string> $enumValues
	 */
	public function __construct(
		public string $name,
		public string $type,
		public bool $isNullable = false,
		public array $enumValues = [],
		public ?IdStrategy $idStrategy = null,
	) {
	}

	public function isId(): bool
	{
		return $this->idStrategy !== null;
	}

	public function isEnum(): bool
	{
		return $this->type === 'enum';
	}

	public function isPassword(): bool
	{
		return str_contains(strtolower($this->name), 'password');
	}

	public function isTimestamp(): bool
	{
		return str_ends_with($this->name, '_at');
	}

	public function phpType(): string
	{
		if ($this->idStrategy) {
			$type = $this->idStrategy->phpType();
			return $this->isNullable ? "?{$type}" : $type;
		}

		if ($this->isEnum()) {
			return $this->isNullable ? '?string' : 'string';
		}

		$type = ltrim($this->type, '?');
		return $this->isNullable ? "?{$type}" : $type;
	}

	public function studlyName(): string
	{
		return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $this->name)));
	}
}