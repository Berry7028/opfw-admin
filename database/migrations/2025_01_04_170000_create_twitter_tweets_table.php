<?php
// Auto generated by the build:migrations command

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTwitterTweetsTable extends Migration
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

		$tableExists = Schema::hasTable("twitter_tweets");

		$indexes = $tableExists ? $this->getIndexedColumns() : [];
		$columns = $tableExists ? $this->getColumns() : [];

		$func = $tableExists ? "table" : "create";

		Schema::$func("twitter_tweets", function (Blueprint $table) use ($columns, $indexes) {
			!in_array("id", $columns) && $table->integer("id")->autoIncrement(); // primary key
			!in_array("authorId", $columns) && $table->integer("authorId")->nullable();
			!in_array("realUser", $columns) && $table->string("realUser", 50)->nullable();
			!in_array("message", $columns) && $table->text("message")->nullable();
			!in_array("time", $columns) && $table->timestamp("time")->useCurrent();
			!in_array("likes", $columns) && $table->integer("likes")->nullable()->default("0");
			!in_array("is_deleted", $columns) && $table->tinyInteger("is_deleted")->nullable()->default("0");

			!in_array("id", $indexes) && $table->index("id");
			!in_array("time", $indexes) && $table->index("time");
			!in_array("authorId", $indexes) && $table->index("authorId");
			!in_array("message", $indexes) && $table->index("message");
			!in_array("is_deleted", $indexes) && $table->index("is_deleted");
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists("twitter_tweets");
	}

	/**
	 * Get all columns.
	 *
	 * @return array
	 */
	private function getColumns(): array
	{
		$columns = Schema::getConnection()->select("SHOW COLUMNS FROM `twitter_tweets`");

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
		$indexes = Schema::getConnection()->select("SHOW INDEXES FROM `twitter_tweets` WHERE Key_name != 'PRIMARY'");

		return array_map(function ($index) {
			return $index->Column_name;
		}, $indexes);
	}
}