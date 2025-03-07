<?php
// Auto generated by the build:migrations command

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateErrorsClientTable extends Migration
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

		$tableExists = Schema::hasTable("errors_client");

		$indexes = $tableExists ? $this->getIndexedColumns() : [];
		$columns = $tableExists ? $this->getColumns() : [];

		$func = $tableExists ? "table" : "create";

		Schema::$func("errors_client", function (Blueprint $table) use ($columns, $indexes) {
			!in_array("error_id", $columns) && $table->integer("error_id")->autoIncrement(); // primary key
			!in_array("license_identifier", $columns) && $table->string("license_identifier", 50)->nullable();
			!in_array("error_location", $columns) && $table->longText("error_location")->nullable();
			!in_array("error_trace", $columns) && $table->longText("error_trace")->nullable();
			!in_array("error_feedback", $columns) && $table->longText("error_feedback")->nullable();
			!in_array("player_ping", $columns) && $table->integer("player_ping")->nullable();
			!in_array("server_id", $columns) && $table->integer("server_id")->nullable();
			!in_array("server_version", $columns) && $table->string("server_version", 50)->nullable();
			!in_array("timestamp", $columns) && $table->integer("timestamp")->nullable();

			!in_array("license_identifier", $indexes) && $table->index("license_identifier");
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists("errors_client");
	}

	/**
	 * Get all columns.
	 *
	 * @return array
	 */
	private function getColumns(): array
	{
		$columns = Schema::getConnection()->select("SHOW COLUMNS FROM `errors_client`");

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
		$indexes = Schema::getConnection()->select("SHOW INDEXES FROM `errors_client` WHERE Key_name != 'PRIMARY'");

		return array_map(function ($index) {
			return $index->Column_name;
		}, $indexes);
	}
}