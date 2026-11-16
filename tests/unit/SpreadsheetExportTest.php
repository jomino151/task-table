<?php
/**
 * The export must open in Excel/LibreOffice whatever editors typed into an event.
 * A single character that is illegal in XML 1.0 makes the whole file "corrupt".
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class SpreadsheetExportTest extends TestCase {

	/**
	 * Column => value. Each value is something real event text contains.
	 */
	private const ROW = array(
		'id'              => '7',
		'was_angefragt'   => "Workshop\x0Bzweite Zeile",            // Word/PDF paste: vertical tab as soft line break
		'kontext'         => 'Ä & Ö <b>fett</b> "Zitat" \'x\'',       // markup and entities
		'name_hochschule' => "=HYPERLINK(\"http://evil.test\",\"x\")", // formula-looking text
		'tn_anzahl'       => '+49 30 123',
		'zeitpunkt'       => "-1\r\n@SUM(A1)",                       // CRLF from a textarea
		'auftragdetails'  => "Tab\there\x1Fend\x00",                 // other C0 controls
		'wer_macht'       => 'Sam',
		'category_id'     => '1',
		'category_name'   => 'Allgemein',
		'archived'        => '0',
		'created_at'      => '2026-11-01 10:00:00',
		'updated_at'      => '2026-11-02 10:00:00',
	);

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_tempnam' )->alias(
			function () {
				return tempnam( sys_get_temp_dir(), 'tt' );
			}
		);
		Functions\when( 'wp_delete_file' )->alias( 'unlink' );
		$GLOBALS['wpdb']       = new Task_Table_Fake_Wpdb();
		$GLOBALS['wpdb']->rows = array( self::ROW );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * What a spreadsheet app shows for a stored value: newlines normalised, XML-illegal
	 * control characters gone, everything else verbatim (no formula evaluation).
	 */
	private static function expected_cells() {
		$cells = array();
		foreach ( self::ROW as $column => $value ) {
			$value            = str_replace( "\r\n", "\n", $value );
			$cells[ $column ] = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $value );
		}
		return $cells;
	}

	private static function parse_xml( $xml ) {
		$doc = new DOMDocument();
		$ok  = @$doc->loadXML( $xml ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- the assertion reports it.
		self::assertTrue( $ok, 'export is not well-formed XML: ' . ( libxml_get_last_error() ? libxml_get_last_error()->message : '' ) );
		return $doc;
	}

	public function test_xlsx_opens_and_every_cell_keeps_its_value_and_column() {
		$sheet = task_table_generate_events_spreadsheet( 'all', 'xlsx' );

		$file = tempnam( sys_get_temp_dir(), 'tt' );
		file_put_contents( $file, $sheet['content'] );
		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $file ) );
		$parts = array( '[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml' );
		foreach ( $parts as $part ) {
			self::parse_xml( $zip->getFromName( $part ) );
		}
		$doc = self::parse_xml( $zip->getFromName( 'xl/worksheets/sheet1.xml' ) );
		$zip->close();
		unlink( $file );

		$xpath = new DOMXPath( $doc );
		$xpath->registerNamespace( 's', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
		$this->assertSame( 0, $xpath->query( '//s:f' )->length, 'no cell may contain a formula' );

		// Header row says which column letter holds which field; the data row must line up with it.
		$header = array();
		foreach ( $xpath->query( '//s:row[@r="1"]/s:c' ) as $cell ) {
			$header[ preg_replace( '/\d+$/', '', $cell->getAttribute( 'r' ) ) ] = $cell->textContent;
		}
		$this->assertSame( array_keys( self::ROW ), array_values( $header ) );
		$this->assertSame( 'ABCDEFGHIJKLM', implode( '', array_keys( $header ) ) );

		$data = array();
		foreach ( $xpath->query( '//s:row[@r="2"]/s:c' ) as $cell ) {
			$this->assertSame( 'inlineStr', $cell->getAttribute( 't' ) );
			$data[ $header[ preg_replace( '/\d+$/', '', $cell->getAttribute( 'r' ) ) ] ] = $cell->textContent;
		}
		$this->assertSame( self::expected_cells(), $data );
	}

	public function test_spreadsheetml_fallback_opens_and_keeps_values() {
		$sheet = task_table_generate_events_spreadsheet( 'all', 'xml' );

		$doc   = self::parse_xml( $sheet['content'] );
		$xpath = new DOMXPath( $doc );
		$xpath->registerNamespace( 'ss', 'urn:schemas-microsoft-com:office:spreadsheet' );
		$rows = $xpath->query( '//ss:Row' );
		$this->assertSame( 2, $rows->length );

		$data = array();
		foreach ( $xpath->query( 'ss:Cell/ss:Data', $rows->item( 1 ) ) as $i => $cell ) {
			$this->assertSame( 'String', $cell->getAttributeNS( 'urn:schemas-microsoft-com:office:spreadsheet', 'Type' ) );
			$data[ array_keys( self::ROW )[ $i ] ] = $cell->textContent;
		}
		$this->assertSame( self::expected_cells(), $data );
	}
}
