<?php
// Auto generated by the build:migrations command

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFpsStatisticsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Make enums work pre laravel 10
		Schema::getConnection()->getDoctrineConnection()->getDatabasePlatform()->registerDoctrineTypeMapping("enum", "string");

		$tableExists = Schema::hasTable("fps_statistics");

		$indexes = $tableExists ? $this->getIndexedColumns() : [];
		$columns = $tableExists ? $this->getColumns() : [];

		$func = $tableExists ? "table" : "create";

		Schema::$func("fps_statistics", function (Blueprint $table) use ($columns, $indexes) {
			!in_array("date", $columns) && $table->string("date", 50)->primary(); // primary key
			!in_array("minimum", $columns) && $table->integer("minimum")->nullable();
			!in_array("maximum", $columns) && $table->integer("maximum")->nullable();
			!in_array("average", $columns) && $table->integer("average")->nullable();
			!in_array("count", $columns) && $table->integer("count")->nullable()->default("0");
			!in_array("lag_spikes", $columns) && $table->integer("lag_spikes")->nullable();
			!in_array("average_1_percent", $columns) && $table->integer("average_1_percent")->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists("fps_statistics");
	}

	/**
	 * Get all columns.
	 *
	 * @return array
	 */
	private function getColumns(): array
	{
		$columns = Schema::getConnection()->select("SHOW COLUMNS FROM `fps_statistics`");

		return array_map(function ($column) {
			return $column->Field;
		}, $columns);
	}

	/**
	 * Get all indexed columns.
	 *
	 * @return array
	 */
	private function getIndexedColumns(): array
	{
		$indexes = Schema::getConnection()->select("SHOW INDEXES FROM `fps_statistics` WHERE Key_name != 'PRIMARY'");

		return array_map(function ($index) {
			return $index->Column_name;
		}, $indexes);
	}
}