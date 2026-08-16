<?php
/**
 *
 * Group Subscription. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Steve Guidetti, https://github.com/stevotvr
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace stevotvr\groupsub\tests\operator;

class currency_test extends \phpbb_test_case
{
	/**
	 * @var \stevotvr\groupsub\operator\currency
	 */
	protected $currency_operator;

	/**
	 * @var \phpbb\config\config|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected $config;

	/**
	 * @var \Symfony\Component\DependencyInjection\ContainerInterface|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected $container;

	/**
	 * @var \phpbb\db\driver\driver_interface|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected $db;

	/**
	 * @var \phpbb\language\language|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected $language;

	/**
	 * Sample currencies array.
	 *
	 * @var array
	 */
	protected $currencies = array(
		'USD' => array(
			'name'            => 'US Dollar',
			'symbol'          => '$',
			'symbol_first'    => true,
			'subunit_to_unit' => 100,
		),
		'EUR' => array(
			'name'            => 'Euro',
			'symbol'          => '€',
			'symbol_first'    => false,
			'subunit_to_unit' => 100,
		),
		'JPY' => array(
			'name'            => 'Japanese Yen',
			'symbol'          => '¥',
			'symbol_first'    => true,
			'subunit_to_unit' => 1,
		),
	);

	public function setUp(): void
	{
		parent::setUp();

		$this->config = $this->getMockBuilder('\phpbb\config\config')
			->disableOriginalConstructor()
			->getMock();

		$this->container = $this->getMockBuilder('\Symfony\Component\DependencyInjection\ContainerInterface')
			->getMock();

		$this->db = $this->getMockBuilder('\phpbb\db\driver\driver_interface')
			->getMock();

		$this->language = $this->getMockBuilder('\phpbb\language\language')
			->disableOriginalConstructor()
			->getMock();

		$this->language->expects($this->any())
			->method('lang')
			->willReturnCallback(function ($key) {
				switch ($key) {
					case 'GROUPSUB_DECIMAL_SEPARATOR':
						return '.';
					case 'GROUPSUB_THOUSANDS_SEPARATOR':
						return ',';
					default:
						return $key;
				}
			});

		$this->currency_operator = new \stevotvr\groupsub\operator\currency(
			$this->config,
			$this->container,
			$this->db,
			$this->language,
			$this->currencies,
			'phpbb_groupsub_pkgs',
			'phpbb_groupsub_actions',
			'phpbb_groupsub_terms',
			'phpbb_groupsub_subs'
		);
	}

	public function test_get_currencies()
	{
		$result = $this->currency_operator->get_currencies();
		$this->assertArrayHasKey('USD', $result);
		$this->assertArrayHasKey('EUR', $result);
		$this->assertArrayHasKey('JPY', $result);
	}

	public function test_is_valid()
	{
		$this->assertTrue($this->currency_operator->is_valid('USD'));
		$this->assertTrue($this->currency_operator->is_valid('EUR'));
		$this->assertFalse($this->currency_operator->is_valid('XYZ'));
	}

	public function test_format_value()
	{
		$formatted = $this->currency_operator->format_value('USD', 2550, false, false);
		$this->assertEquals('25.50', $formatted);

		$formatted_large = $this->currency_operator->format_value('USD', 125000, true, false);
		$this->assertEquals('1,250.00', $formatted_large);
	}

	public function test_format_price()
	{
		$price_usd = $this->currency_operator->format_price('USD', 1500);
		$this->assertEquals('$15.00&nbsp;USD', $price_usd);

		$price_eur = $this->currency_operator->format_price('EUR', 2000);
		$this->assertEquals('20.00€&nbsp;EUR', $price_eur);
	}

	public function test_parse_value()
	{
		$parsed = $this->currency_operator->parse_value('USD', '25.50');
		$this->assertEquals(2550, $parsed);

		$parsed_large = $this->currency_operator->parse_value('USD', '1,250.00');
		$this->assertEquals(125000, $parsed_large);
	}
}
