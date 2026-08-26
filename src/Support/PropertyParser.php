<?php

namespace Yntech\DomainForge\Support;

final class PropertyParser
{
	/** @var array<string> */
	private array $warnings = [];

	/**
	 * @return array<Property>
	 */
	public function parse(?string $propsString): array
	{
		$this->warnings = [];

		if (empty($propsString)) {
			return [];
		}

		$properties = [];
		$parts = explode(',', $propsString);

		foreach ($parts as $part) {
			$part = trim($part);

			if ($part === '') {
				continue;
			}

			if (!str_contains($part, ':')) {
				$this->warnings[] = "Property '{$part}' has invalid format. Expected name:type";
				continue;
			}

			[$name, $type] = array_map('trim', explode(':', $part, 2));

			if (!$this->isValidPropertyName($name)) {
				$this->warnings[] = "Invalid property name '{$name}'. Skipping.";
				continue;
			}

			$property = $this->buildProperty($name, $type);

			if ($property !== null) {
				$properties[] = $property;
			}
		}

		return $properties;
	}

	/**
	 * @return array<string>
	 */
	public function warnings(): array
	{
		return $this->warnings;
	}

	private function buildProperty(string $name, string $type): ?Property
	{
		if (preg_match('/^enum\[(.+)\]$/', $type, $matches)) {
			$values = array_map('trim', explode('|', $matches[1]));

			foreach ($values as $value) {
				if (!preg_match('/^[a-z][a-zA-Z0-9_]*$/', $value)) {
					$this->warnings[] = "Invalid enum value '{$value}' in property '{$name}'. Skipping.";
					return null;
				}
			}

			return new Property(
				name: $name,
				type: 'enum',
				enumValues: $values,
			);
		}

		if ($name === 'id') {
			return $this->buildIdProperty($type);
		}

		$isNullable = str_starts_with($type, '?');
		$cleanType = ltrim($type, '?');

		$allowed = ['string', 'int', 'float', 'bool'];

		if (!in_array($cleanType, $allowed, true)) {
			$this->warnings[] = "Unsupported type '{$type}' for property '{$name}'. Skipping.";
			return null;
		}

		return new Property(
			name: $name,
			type: $cleanType,
			isNullable: $isNullable,
		);
	}

	private function buildIdProperty(string $type): ?Property
	{
		if ($type === 'string') {
			$this->warnings[] = "'id:string' is deprecated. Use 'id:uuid' or 'id:ulid' instead. Falling back to uuid.";
			$type = 'uuid';
		}

		$strategy = match ($type) {
			'uuid' => IdStrategy::Uuid,
			'uuidv7' => IdStrategy::UuidV7,
			'ulid' => IdStrategy::Ulid,
			'int' => IdStrategy::Int,
			default => null,
		};

		if ($strategy === null) {
			$this->warnings[] = "Invalid id type '{$type}'. Allowed: uuid, uuidv7, ulid, int";
			return null;
		}

		return new Property(
			name: 'id',
			type: $strategy->value,
			idStrategy: $strategy,
		);
	}

	private function isValidPropertyName(string $name): bool
	{
		return (bool) preg_match('/^[a-z][a-zA-Z0-9_]*$/', $name);
	}
}