<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class DatetimeDateBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function parseValue(string $value, ?Column $column = null): string {
		return json_encode($this->isValidDate($value, 'Y-m-d') ? $value : '');
	}

	public function canBeParsed(string $value, ?Column $column = null): bool {
		return $this->isValidDate($value, 'Y-m-d');
	}

}
