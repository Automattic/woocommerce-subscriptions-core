<?php
/**
 *
 * @since 2.0
 */
class WCS_Time_Functions_Unit_Tests extends WCS_Unit_Test_Case {

	var $date_display_format = 'Y-m-d H:i:s';

	/**
	 * Testing wcs_get_subscription_period_strings()
	 *
	 * @since 2.0
	 */
	public function test_wcs_get_subscription_period_strings() {
		// Expected output for multiple test cases
		$expected_output_base_case = array(
			'day'   => 'day',
			'week'  => 'week',
			'month' => 'month',
			'year'  => 'year',
		);
		// test default case
		$this->assertEquals( $expected_output_base_case, wcs_get_subscription_period_strings() );
		$this->assertEquals( $expected_output_base_case, wcs_get_subscription_period_strings( 1 ) );

		// Test simple case with no period specified
		$expected_output = array(
			'day'   => '6 days',
			'week'  => '6 weeks',
			'month' => '6 months',
			'year'  => '6 years',
		);
		$this->assertEquals( $expected_output, wcs_get_subscription_period_strings( 6 ) );

		// default period, interval number 0
		$expected_output = array(
			'day'   => '0 days',
			'week'  => '0 weeks',
			'month' => '0 months',
			'year'  => '0 years',
		);
		$this->assertEquals( $expected_output, wcs_get_subscription_period_strings( 0 ) );

		// Test day period
		$this->assertEquals( 'day', wcs_get_subscription_period_strings( 1, 'day' ) );
		$this->assertEquals( '2 days', wcs_get_subscription_period_strings( 2, 'day' ) );
		$this->assertEquals( '0 days', wcs_get_subscription_period_strings( 0, 'day' ) );

		// Test week as period
		$this->assertEquals( 'week', wcs_get_subscription_period_strings( 1, 'week' ) );
		$this->assertEquals( '0 weeks', wcs_get_subscription_period_strings( 0, 'week' ) );
		$this->assertEquals( '2573485734853 weeks', wcs_get_subscription_period_strings( 2573485734853, 'week' ) );

		// Test month output
		$this->assertEquals( 'month', wcs_get_subscription_period_strings( 1, 'month' ) );
		$this->assertEquals( '0 months', wcs_get_subscription_period_strings( 0, 'month' ) );
		$this->assertEquals( '5 months', wcs_get_subscription_period_strings( 5, 'month' ) );

		// Test year period
		$this->assertEquals( 'year', wcs_get_subscription_period_strings( 1, 'year' ) );
		$this->assertEquals( '0 years', wcs_get_subscription_period_strings( 0, 'year' ) );
		$this->assertEquals( '3.5 years', wcs_get_subscription_period_strings( 3.5, 'year' ) );

		// Broken cases
		$this->assertEquals( 'hello world years', wcs_get_subscription_period_strings( 'hello world', 'year' ) );
	}

	/**
	 * Testing wcs_get_subscription_trial_period_strings()
	 *
	 * @since 2.0
	 */
	public function test_wcs_get_subscription_trial_period_strings() {
		// base cases
		$expected_output = array(
			'day'   => '1 day',
			'week'  => '1 week',
			'month' => '1 month',
			'year'  => '1 year',
		);
		$this->assertEquals( $expected_output, wcs_get_subscription_trial_period_strings() );
		$this->assertEquals( $expected_output, wcs_get_subscription_trial_period_strings( 1 ) );

		// valid cases with no period string given
		$expected_output = array(
			'day'   => 'a 5-day',
			'week'  => 'a 5-week',
			'month' => 'a 5-month',
			'year'  => 'a 5-year',
		);
		$this->assertEquals( $expected_output, wcs_get_subscription_trial_period_strings( 5 ) );

		// default period, interval number 0
		$expected_output = array(
			'day'   => 'a 0-day',
			'week'  => 'a 0-week',
			'month' => 'a 0-month',
			'year'  => 'a 0-year',
		);
		$this->assertEquals( $expected_output, wcs_get_subscription_trial_period_strings( 0 ) );

		// Test day period
		$this->assertEquals( '1 day', wcs_get_subscription_trial_period_strings( 1, 'day' ) );
		$this->assertEquals( 'a 2-day', wcs_get_subscription_trial_period_strings( 2, 'day' ) );
		$this->assertEquals( 'a 0-day', wcs_get_subscription_trial_period_strings( 0, 'day' ) );

		// Test week as period
		$this->assertEquals( '1 week', wcs_get_subscription_trial_period_strings( 1, 'week' ) );
		$this->assertEquals( 'a 5.9-week', wcs_get_subscription_trial_period_strings( 5.9, 'week' ) );
		$this->assertEquals( 'a 0-week', wcs_get_subscription_trial_period_strings( 0, 'week' ) );

		// Test month output
		$this->assertEquals( '1 month', wcs_get_subscription_trial_period_strings( 1, 'month' ) );
		$this->assertEquals( 'a 0-month', wcs_get_subscription_trial_period_strings( 0, 'month' ) );
		$this->assertEquals( 'a 5-month', wcs_get_subscription_trial_period_strings( 5, 'month' ) );

		// Test year period
		$this->assertEquals( '1 year', wcs_get_subscription_trial_period_strings( 1, 'year' ) );
		$this->assertEquals( 'a 0-year', wcs_get_subscription_trial_period_strings( 0, 'year' ) );
		$this->assertEquals( 'a 7-year', wcs_get_subscription_trial_period_strings( 7, 'year' ) );
	}

	/**
	 * Testing wcs_get_subscription_ranges()
	 *
	 * @since 2.0
	 */
	public function test_wcs_get_subscription_ranges() {

		$expected_output_years = array(
			0 => 'Never expire',
			1 => '1 year',
			2 => '2 years',
			3 => '3 years',
			4 => '4 years',
			5 => '5 years',
		);
		$this->assertEquals( $expected_output_years, wcs_get_subscription_ranges( 'year' ) );

		$expected_output_months = array(
			 0 => 'Never expire', 1 => '1 month',    2 => '2 months',   3 => '3 months',
			 4 => '4 months',     5 => '5 months',   6 => '6 months',   7 => '7 months',
			 8 => '8 months',     9 => '9 months',  10 => '10 months', 11 => '11 months',
			12 => '12 months',   13 => '13 months', 14 => '14 months', 15 => '15 months',
			16 => '16 months',   17 => '17 months', 18 => '18 months', 19 => '19 months',
			20 => '20 months',   21 => '21 months', 22 => '22 months', 23 => '23 months',
			24 => '24 months',
		);
		$this->assertEquals( $expected_output_months, wcs_get_subscription_ranges( 'month' ) );

		$expected_output_weeks = array(
			 0 => 'Never expire', 1 => '1 week',    2 => '2 weeks',   3 => '3 weeks',
			 4 => '4 weeks',      5 => '5 weeks',   6 => '6 weeks',   7 => '7 weeks',
			 8 => '8 weeks',      9 => '9 weeks',  10 => '10 weeks', 11 => '11 weeks',
			12 => '12 weeks',    13 => '13 weeks', 14 => '14 weeks', 15 => '15 weeks',
			16 => '16 weeks',    17 => '17 weeks', 18 => '18 weeks', 19 => '19 weeks',
			20 => '20 weeks',    21 => '21 weeks', 22 => '22 weeks', 23 => '23 weeks',
			24 => '24 weeks',    25 => '25 weeks', 26 => '26 weeks', 27 => '27 weeks',
			28 => '28 weeks',    29 => '29 weeks', 30 => '30 weeks', 31 => '31 weeks',
			32 => '32 weeks',    33 => '33 weeks', 34 => '34 weeks', 35 => '35 weeks',
			36 => '36 weeks',    37 => '37 weeks', 38 => '38 weeks', 39 => '39 weeks',
			40 => '40 weeks',    41 => '41 weeks', 42 => '42 weeks', 43 => '43 weeks',
			44 => '44 weeks',    45 => '45 weeks', 46 => '46 weeks', 47 => '47 weeks',
			48 => '48 weeks',    49 => '49 weeks', 50 => '50 weeks', 51 => '51 weeks',
			52 => '52 weeks',
		);
		$this->assertEquals( $expected_output_weeks, wcs_get_subscription_ranges( 'week' ) );

		$expected_output_days = array(
			 0 => 'Never expire', 1 => '1 day',    2 => '2 days',   3 => '3 days',
			 4 => '4 days',       5 => '5 days',   6 => '6 days',   7 => '7 days',
			 8 => '8 days',       9 => '9 days',  10 => '10 days', 11 => '11 days',
			12 => '12 days',     13 => '13 days', 14 => '14 days', 15 => '15 days',
			16 => '16 days',     17 => '17 days', 18 => '18 days', 19 => '19 days',
			20 => '20 days',     21 => '21 days', 22 => '22 days', 23 => '23 days',
			24 => '24 days',     25 => '25 days', 26 => '26 days', 27 => '27 days',
			28 => '28 days',     29 => '29 days', 30 => '30 days', 31 => '31 days',
			32 => '32 days',     33 => '33 days', 34 => '34 days', 35 => '35 days',
			36 => '36 days',     37 => '37 days', 38 => '38 days', 39 => '39 days',
			40 => '40 days',     41 => '41 days', 42 => '42 days', 43 => '43 days',
			44 => '44 days',     45 => '45 days', 46 => '46 days', 47 => '47 days',
			48 => '48 days',     49 => '49 days', 50 => '50 days', 51 => '51 days',
			52 => '52 days',     53 => '53 days', 54 => '54 days', 55 => '55 days',
			56 => '56 days',     57 => '57 days', 58 => '58 days', 59 => '59 days',
			60 => '60 days',     61 => '61 days', 62 => '62 days', 63 => '63 days',
			64 => '64 days',     65 => '65 days', 66 => '66 days', 67 => '67 days',
			68 => '68 days',     69 => '69 days', 70 => '70 days', 71 => '71 days',
			72 => '72 days',     73 => '73 days', 74 => '74 days', 75 => '75 days',
			76 => '76 days',     77 => '77 days', 78 => '78 days', 79 => '79 days',
			80 => '80 days',     81 => '81 days', 82 => '82 days', 83 => '83 days',
			84 => '84 days',     85 => '85 days', 86 => '86 days', 87 => '87 days',
			88 => '88 days',     89 => '89 days', 90 => '90 days',
		);
		$this->assertEquals( $expected_output_days, wcs_get_subscription_ranges( 'day' ) );

		// base case
		$expected_output = array(
			'day' => $expected_output_days,
			'week' => $expected_output_weeks,
			'month' => $expected_output_months,
			'year' => $expected_output_years,
		);
		$this->assertEquals( $expected_output, wcs_get_subscription_ranges() );

	}

	/**
	 * Testing wcs_get_subscription_period_interval_strings()
	 *
	 * @since 2.0
	 */
	public function test_wcs_get_period_interval_strings() {
		$expected_intervals = array(
			1 => 'every',
			2 => 'every 2nd',
			3 => 'every 3rd',
			4 => 'every 4th',
			5 => 'every 5th',
			6 => 'every 6th',
		);

		$this->assertEquals( $expected_intervals, wcs_get_subscription_period_interval_strings(), '[FAILED] interval = empty');
		$this->assertEquals( $expected_intervals, wcs_get_subscription_period_interval_strings(0), '[FAILED] interval = 0' );

		$this->assertEquals( 'every', wcs_get_subscription_period_interval_strings(1), '[FAILED] interval = 1'  );
		$this->assertEquals( 'every 2nd', wcs_get_subscription_period_interval_strings(2), '[FAILED] interval = 2'  );
		$this->assertEquals( 'every 3rd', wcs_get_subscription_period_interval_strings(3), '[FAILED] interval = 3'  );
		$this->assertEquals( 'every 4th', wcs_get_subscription_period_interval_strings(4), '[FAILED] interval = 4'  );
		$this->assertEquals( 'every 5th', wcs_get_subscription_period_interval_strings(5), '[FAILED] interval = 5'  );
		$this->assertEquals( 'every 6th', wcs_get_subscription_period_interval_strings(6), '[FAILED] interval = 6'  );
	}

	/**
	 * Function: wcs_get_available_time_periods
	 *
	 * @since 2.0
	 */
	public function test_wcs_get_available_time_periods() {

		$singular_output = array(
			'day'   => 'day',
			'week'  => 'week',
			'month' => 'month',
			'year'  => 'year',
		);
		$this->assertEquals( $singular_output, wcs_get_available_time_periods() );
		$this->assertEquals( $singular_output, wcs_get_available_time_periods( 'singular' ) );

		$plurals_output = array(
			'day'   => 'days',
			'week'  => 'weeks',
			'month' => 'months',
			'year'  => 'years',
		);
		$this->assertEquals( $plurals_output, wcs_get_available_time_periods( 'plural' ) );
		$this->assertEquals( $plurals_output, wcs_get_available_time_periods( 'sdsfhsudhfdfhsudf' ) );

		// Broken tests
		$this->assertEquals( $plurals_output, wcs_get_available_time_periods( true ), 'boolean true' );
		$this->assertEquals( $plurals_output, wcs_get_available_time_periods( 'true' ), 'true: string' );
		$this->assertEquals( $plurals_output, wcs_get_available_time_periods( 123 ), '123:integer' );

	}

	/**
	 * wcs_get_subscription_trial_lengths
	 *
	 * @since 2.0
	 */
	public function test_wcs_get_subscription_trial_lengths() {

		$trial_lengths = wcs_get_subscription_ranges();
		$trial_lengths['year'][0] = 'no';
		$trial_lengths['month'][0] = 'no';
		$trial_lengths['week'][0] = 'no';
		$trial_lengths['day'][0] = 'no';

		$this->assertEquals( $trial_lengths, wcs_get_subscription_trial_lengths() );
		$this->assertEquals( $trial_lengths['year'], wcs_get_subscription_trial_lengths( 'year' ) );
		$this->assertEquals( $trial_lengths['month'], wcs_get_subscription_trial_lengths( 'month' ) );
		$this->assertEquals( $trial_lengths['week'], wcs_get_subscription_trial_lengths( 'week' ) );
		$this->assertEquals( $trial_lengths['day'], wcs_get_subscription_trial_lengths( 'day' ) );

	}

	public function wcs_add_time_data() {
		return array(
			// test adding 5 days to 28th Dec 2014, should return the 5th Jan 2015.
			array( '2014-12-28 01:01:01', '2015-01-02 01:01:01', 5, 'day' ),
			// Leap year test adding 8 days to 21st Feb 2016, should return the 29th Feb 2016.
			array( '2016-02-21 01:10:01', '2016-02-29 01:10:01', 8, 'day' ),
			// Non-leap year test adding 1 day to 28st Feb 2015, should return the 1st Mar 2015.
			array( '2015/02/28', '2015/03/01', 1, 'day' ),
			// Test adding 1 week to 21st Feb 2015, should return the 28th Feb 2015.
			array( '2015/02/21', '2015/02/28', 1, 'week' ),
			// Test adding 52 weeks to 22nd Feb 2015, should return the 29th Feb 2016 - not sure about this test
			array( '2015-02-02 02:02:02', '2016-02-1 02:02:02', 52, 'week' ),
			// Test adding 4 years to 29nd Feb 2016, should return the 29th Feb 2020
			array( '2016/02/29', '2020/02/29', 4, 'year' ),
			// Test adding 1 year to 7th Oct 2018, should return the 7th Oct 2019
			array( '2018/10/07', '2019/10/07', 1, 'year' ),
			// Test adding maximum valid sub period (6 years) to 31st Dec 2030, should return the 31st Dec 2036
			array( '2030/12/31', '2036/12/31', 6, 'year' ),

			// Test with the new argument $timezone_behaviour
			array( '2019-12-28 01:01:01', '2020-01-02 01:01:01', 5, 'day', 'no_offset' ),
			array( '2019-12-28 01:01:01', '2020-01-02 01:01:01', 5, 'day', 'offset_site_time' ),
			array( '2020-02-21 01:01:01', '2020-02-29 01:01:01', 8, 'day', 'no_offset' ),
			array( '2020-02-21 01:01:01', '2020-02-29 01:01:01', 8, 'day', 'offset_site_time' ),
			array( '2018-02-28 01:01:01', '2018-03-01 01:01:01', 1, 'day', 'no_offset' ),
			array( '2018-02-28 01:01:01', '2018-03-01 01:01:01', 1, 'day', 'offset_site_time' ),
			array( '2018-02-21 01:01:01', '2018-02-28 01:01:01', 1, 'week', 'no_offset' ),
			array( '2018-02-21 01:01:01', '2018-02-28 01:01:01', 1, 'week', 'offset_site_time' ),
			array( '2018-02-02 01:01:01', '2019-02-01 01:01:01', 52, 'week', 'no_offset' ),
			array( '2018-02-02 01:01:01', '2019-02-01 01:01:01', 52, 'week', 'offset_site_time' ),
			array( '2018-02-28 01:01:01', '2022-02-28 01:01:01', 4, 'year', 'no_offset' ),
			array( '2018-02-28 01:01:01', '2022-02-28 01:01:01', 4, 'year', 'offset_site_time' ),
			array( '2020-02-29 01:01:01', '2021-03-01 01:01:01', 1, 'year', 'no_offset' ),
			array( '2020-02-29 01:01:01', '2021-03-01 01:01:01', 1, 'year', 'offset_site_time' ),
			array( '2030-12-31 01:01:01', '2036-12-31 01:01:01', 6, 'year', 'no_offset' ),
			array( '2030-12-31 01:01:01', '2036-12-31 01:01:01', 6, 'year', 'offset_site_time' ),

		);
	}

	/**
	 * Testing wcs_add_time()
	 *
	 * @dataProvider wcs_add_time_data
	 * @since 2.0
	 */
	public function test_wcs_add_time( $from, $expected, $interval, $period, $timezone_behaviour = 'no_offset' ) {
		$actual_result = wcs_add_time( $interval, $period, strtotime( $from ), $timezone_behaviour );
		$this->assertEquals( strtotime( $expected ), $actual_result, '[FAILED]: Adding ' . $interval . ' ' . $period . ' to ' . $from . ' and expecting ' . $expected . ', but getting ' . gmdate( $this->date_display_format,  $actual_result ) );
	}

	public function add_months_test_data() {
		return array(
			// Test adding 0 months to 2nd Jul 2018, should return the 2nd Jul 2018.
			array( '2018/07/02', '2018/07/02', 0 ),
			// Leap year test adding 1 month to 30 Jan 2016, should return the 29th Feb 2016.
			array( '2016/01/30', '2016/02/29', 1 ),
			// Leap year test adding 12 months to 29 Feb 2016, should return the 28th Feb 2017.
			array( '2016/02/29', '2017/02/28', 12 ),
			// Test adding 8 months to 15 Jan 2014, should return the 15th Spt.
			array( '2014/01/15', '2014/09/15', 8 ),
			// Test adding 1 month to 15 Jan 2014, should return the 15th Feb.
			array( '2014/01/15', '2014/02/15', 1 ),
			// Test adding 1 month to 30 Jan 2014, should return the end of Feb (28th).
			array( '2014/01/31', '2014/02/28', 1 ),
			// Test adding 3 months to 31 Jan 2014, should return the end of April (30th).
			array( '2014/01/31', '2014/04/30', 3 ),
			// Test adding 6 months to 30 Apr 2014, should return the end of Oct (31st).
			array( '2014/04/30', '2014/10/31', 6 ),
			// Test adding 61 months to 30 Sept 2014, should return 31 Oct 2019
			array( '2014/09/30', '2019/10/31', 61 ),

			// Use the new argument $timezone_behaviour
			array( '2018/07/02', '2018/07/02', 0, 'no_offset' ),
			array( '2018/07/02', '2018/07/02', 0, 'offset_site_time' ),
			array( '2018/07/02 11:59:59', '2018/07/02 11:59:59', 0, 'offset_site_time' ),
			// Leap year test adding 1 month to 30 Jan 2016, should return the 29th Feb 2016.
			array( '2016/01/30 11:59:59', '2016/02/29 11:59:59', 1, 'no_offset' ),
			array( '2016/01/30 11:59:59', '2016/02/29 11:59:59', 1, 'offset_site_time' ),
			// Leap year test adding 12 months to 29 Feb 2016, should return the 28th Feb 2017.
			array( '2016/02/29', '2017/02/28', 12, 'no_offset' ),
			array( '2016/02/29', '2017/02/28', 12, 'offset_site_time' ),
			// Test adding 8 months to 15 Jan 2014, should return the 15th Spt.
			array( '2014/01/15', '2014/09/15', 8, 'no_offset' ),
			array( '2014/01/15', '2014/09/15', 8, 'offset_site_time' ),
			// Test adding 1 month to 15 Jan 2014, should return the 15th Feb.
			array( '2014/01/15', '2014/02/15', 1, 'no_offset' ),
			array( '2014/01/15', '2014/02/15', 1, 'offset_site_time' ),
			// Test adding 1 month to 30 Jan 2014, should return the end of Feb (28th).
			array( '2014/01/31', '2014/02/28', 1, 'no_offset' ),
			array( '2014/01/31', '2014/02/28', 1, 'offset_site_time' ),
			// Test adding 3 months to 31 Jan 2014, should return the end of April (30th).
			array( '2014/01/31', '2014/04/30', 3, 'no_offset' ),
			array( '2014/01/31', '2014/04/30', 3, 'offset_site_time' ),
			// Test adding 6 months to 30 Apr 2014, should return the end of Oct (31st).
			array( '2014/04/30', '2014/10/31', 6, 'no_offset' ),
			array( '2014/04/30', '2014/10/31', 6, 'offset_site_time' ),
			// Test adding 61 months to 30 Sept 2014, should return 31 Oct 2019
			array( '2014/09/30', '2019/10/31', 61, 'no_offset' ),
			array( '2014/09/30', '2019/10/31', 61, 'offset_site_time' ),

		);
	}

	/**
	 * Testing wcs_add_months()
	 *
	 * @dataProvider add_months_test_data
	 * @since 2.0
	 */
	public function test_wcs_add_months( $from_date, $expected, $months_to_add, $timezone_behaviour = 'no_offset' ) {
		$actual_result = wcs_add_months( strtotime( $from_date ), $months_to_add, $timezone_behaviour );
		$this->assertEquals( strtotime( $expected ), $actual_result, '[FAILED]: Adding ' . $months_to_add . ' months to ' . $from_date . ' and expecting ' . $expected . ', but getting ' . gmdate( $this->date_display_format,  $actual_result ) );
	}

	/**
	 * Testing wcs_add_months() for various timezones
	 *
	 *
	 * @since 3.0.6
	 */
	public function add_months_test_data_for_timezones() {
		// For various time zones
 		// Timezone, Start date, End date, Expected date, Months to Add, Timezone behaviour
		return array(
			// UTC+10
			array( 'Australia/Brisbane', '2020-06-30 00:00:00', '2020-07-31 00:00:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-06-29 00:00:00', '2020-07-29 00:00:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-08-31 00:00:00', '2020-09-30 00:00:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-06-30 04:30:00', '2020-07-31 04:30:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-06-29 04:30:00', '2020-07-29 04:30:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-08-31 04:30:00', '2020-09-30 04:30:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-06-30 12:45:00', '2020-07-31 12:45:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-06-29 12:45:00', '2020-07-29 12:45:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-08-31 12:45:00', '2020-09-30 12:45:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-06-30 21:00:00', '2020-07-31 21:00:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-06-29 21:00:00', '2020-07-29 21:00:00', 1, 'no_offset' ),
			array( 'Australia/Brisbane', '2020-08-31 21:00:00', '2020-09-30 21:00:00', 1, 'no_offset' ),
			// UTC+5:30
			array( 'Asia/Kolkata', '2020-06-30 00:00:00', '2020-07-31 00:00:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-06-29 00:00:00', '2020-07-29 00:00:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-08-31 00:00:00', '2020-09-30 00:00:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-06-30 04:30:00', '2020-07-31 04:30:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-06-29 04:30:00', '2020-07-29 04:30:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-08-31 04:30:00', '2020-09-30 04:30:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-06-30 12:45:00', '2020-07-31 12:45:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-06-29 12:45:00', '2020-07-29 12:45:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-08-31 12:45:00', '2020-09-30 12:45:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-06-30 21:00:00', '2020-07-31 21:00:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-06-29 21:00:00', '2020-07-29 21:00:00', 1, 'no_offset' ),
			array( 'Asia/Kolkata', '2020-08-31 21:00:00', '2020-09-30 21:00:00', 1, 'no_offset' ),
			// UTC-7
			array( 'America/Phoenix', '2020-06-30 00:00:00', '2020-07-31 00:00:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-06-29 00:00:00', '2020-07-29 00:00:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-08-31 00:00:00', '2020-09-30 00:00:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-06-30 04:30:00', '2020-07-31 04:30:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-06-29 04:30:00', '2020-07-29 04:30:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-08-31 04:30:00', '2020-09-30 04:30:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-06-30 12:45:00', '2020-07-31 12:45:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-06-29 12:45:00', '2020-07-29 12:45:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-08-31 12:45:00', '2020-09-30 12:45:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-06-30 21:00:00', '2020-07-31 21:00:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-06-29 21:00:00', '2020-07-29 21:00:00', 1, 'no_offset' ),
			array( 'America/Phoenix', '2020-08-31 21:00:00', '2020-09-30 21:00:00', 1, 'no_offset' ),

			// Apply site time offset
			// UTC+10
			array( 'Australia/Brisbane', '2020-06-30 00:00:00', '2020-07-31 00:00:00', 1, 'offset_site_time' ),
			array( 'Australia/Brisbane', '2020-06-29 00:00:00', '2020-07-29 00:00:00', 1, 'offset_site_time' ),
			array( 'Australia/Brisbane', '2020-08-31 00:00:00', '2020-09-30 00:00:00', 1, 'offset_site_time' ),
			array( 'Australia/Brisbane', '2020-06-30 04:30:00', '2020-07-31 04:30:00', 1, 'offset_site_time' ),
			array( 'Australia/Brisbane', '2020-06-29 04:30:00', '2020-07-29 04:30:00', 1, 'offset_site_time' ),
			array( 'Australia/Brisbane', '2020-08-31 04:30:00', '2020-09-30 04:30:00', 1, 'offset_site_time' ),
			array( 'Australia/Brisbane', '2020-06-30 12:45:00', '2020-07-31 12:45:00', 1, 'offset_site_time' ),
			array( 'Australia/Brisbane', '2020-06-29 12:45:00', '2020-07-29 12:45:00', 1, 'offset_site_time' ), //Different result from 'no_offset'
			array( 'Australia/Brisbane', '2020-08-31 12:45:00', '2020-09-30 12:45:00', 1, 'offset_site_time' ),
			array( 'Australia/Brisbane', '2020-06-30 21:00:00', '2020-07-31 21:00:00', 1, 'offset_site_time' ),
			array( 'Australia/Brisbane', '2020-06-29 21:00:00', '2020-07-30 21:00:00', 1, 'offset_site_time' ), //Different result from 'no_offset'
			array( 'Australia/Brisbane', '2020-08-31 21:00:00', '2020-09-30 21:00:00', 1, 'offset_site_time' ),
			// UTC+5:30
			array( 'Asia/Kolkata', '2020-06-30 00:00:00', '2020-07-31 00:00:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-06-29 00:00:00', '2020-07-29 00:00:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-08-31 00:00:00', '2020-09-30 00:00:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-06-30 04:30:00', '2020-07-31 04:30:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-06-29 04:30:00', '2020-07-29 04:30:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-08-31 04:30:00', '2020-09-30 04:30:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-06-30 12:45:00', '2020-07-31 12:45:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-06-29 12:45:00', '2020-07-29 12:45:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-08-31 12:45:00', '2020-09-30 12:45:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-06-30 21:00:00', '2020-07-31 21:00:00', 1, 'offset_site_time' ),
			array( 'Asia/Kolkata', '2020-06-29 21:00:00', '2020-07-30 21:00:00', 1, 'offset_site_time' ), //Different result from 'no_offset'
			array( 'Asia/Kolkata', '2020-08-31 21:00:00', '2020-09-30 21:00:00', 1, 'offset_site_time' ),
			// UTC-7
			array( 'America/Phoenix', '2020-06-30 00:00:00', '2020-07-30 00:00:00', 1, 'offset_site_time' ), //Different result from 'no_offset'
			array( 'America/Phoenix', '2020-06-29 00:00:00', '2020-07-29 00:00:00', 1, 'offset_site_time' ),
			array( 'America/Phoenix', '2020-08-31 00:00:00', '2020-10-01 00:00:00', 1, 'offset_site_time' ), //Different result from 'no_offset'
			array( 'America/Phoenix', '2020-06-30 04:30:00', '2020-07-30 04:30:00', 1, 'offset_site_time' ),
			array( 'America/Phoenix', '2020-06-29 04:30:00', '2020-07-29 04:30:00', 1, 'offset_site_time' ),
			array( 'America/Phoenix', '2020-08-31 04:30:00', '2020-10-01 04:30:00', 1, 'offset_site_time' ),
			array( 'America/Phoenix', '2020-06-30 12:45:00', '2020-07-31 12:45:00', 1, 'offset_site_time' ),
			array( 'America/Phoenix', '2020-06-29 12:45:00', '2020-07-29 12:45:00', 1, 'offset_site_time' ),
			array( 'America/Phoenix', '2020-08-31 12:45:00', '2020-09-30 12:45:00', 1, 'offset_site_time' ),
			array( 'America/Phoenix', '2020-06-30 21:00:00', '2020-07-31 21:00:00', 1, 'offset_site_time' ),
			array( 'America/Phoenix', '2020-06-29 21:00:00', '2020-07-29 21:00:00', 1, 'offset_site_time' ),
			array( 'America/Phoenix', '2020-08-31 21:00:00', '2020-09-30 21:00:00', 1, 'offset_site_time' )
		);
	}

	/**
	 * Testing wcs_add_months()
	 *
	 * @dataProvider add_months_test_data_for_timezones
	 * @since 2.0
	 */
	public function test1_wcs_add_months( $timezone, $from_date, $expected, $months_to_add, $timezone_behaviour = 'no_offset' ) {
		$original_timezone = get_option( 'timezone_string' );
		update_option( 'timezone_string', $timezone );
		$actual_result = wcs_add_months( strtotime( $from_date ), $months_to_add, $timezone_behaviour );
		$this->assertEquals( strtotime( $expected ), $actual_result, '[FAILED]: Adding ' . $months_to_add . ' months to ' . $from_date . ' and expecting ' . $expected . ', but getting ' . gmdate( $this->date_display_format,  $actual_result ) );
		update_option( 'timezone_string', $original_timezone );
	}

	public function estimate_periods_between_data() {
		return array(
			// how many days between tests
			array( '2015-01-01 01:01:01', '2015-01-02 01:01:02', 'day', 'ceil', 2 ), // 1 day and 1 second test, check how many full days between
			array( '2015-01-01 01:01:01', '2015-01-02 01:01:02', 'day', 'floor', 1 ),
			array( '2015-01-01 01:01:01', '2015-01-02 01:01:01', 'day', 'ceil', 1 ), // 1 day exactly between the two
			array( '2015-01-01 01:01:01', '2015-01-02 01:01:01', 'day', 'floor', 1 ),

			array( '2016-02-21 01:10:01', '2016-02-29 01:10:01', 'day', 'ceil', 8 ), // exactly 8 days
			array( '2016-02-21 01:10:01', '2016-02-29 01:10:01', 'day', 'floor', 8 ),
			array( '2016-02-21 01:10:01', '2016-02-29 02:10:01', 'day', 'ceil', 9 ), // 8 days and 1 hour difference
			array( '2016-02-21 01:10:01', '2016-02-29 02:10:01', 'day', 'floor', 8 ),

			// how many weeks between tests
			array( '2014-01-01 10:00:12', '2014-01-01 10:00:12', 'week', 'ceil', 0 ), // same same
			array( '2014-01-01 10:00:12', '2014-01-01 10:00:12', 'week', 'floor', 0 ),
			array( '2013-01-01 08:07:05', '2013-01-01 08:07:06', 'week', 'ceil', 1 ), // 1 second apart
			array( '2013-01-01 08:07:05', '2013-01-01 08:07:06', 'week', 'floor', 0 ),
			array( '2013-01-01 08:07:06', '2013-01-08 08:07:06', 'week', 'ceil', 1 ), // exactly 1 week apart
			array( '2013-01-01 08:07:06', '2013-01-08 08:07:06', 'week', 'floor', 1 ),

			array( '2015-02-02 02:02:02', '2016-02-01 02:02:02', 'week', 'ceil', 52 ), // exactly 52 weeks between the two dates
			array( '2015-02-02 02:02:02', '2016-02-01 02:02:02', 'week', 'floor', 52 ),
			array( '2015-02-02 02:02:02', '2016-02-02 02:02:02', 'week', 'ceil', 53 ), // 52 weeks and 1 day apart
			array( '2015-02-02 02:02:02', '2016-02-02 02:02:02', 'week', 'floor', 52 ),

			// how many months between tests
			array( '2015-01-01 08:08:08', '2015-01-01 08:08:08', 'month', 'ceil', 0 ),
			array( '2015-01-01 08:08:08', '2015-01-01 08:08:08', 'month', 'floor', 0 ),
			array( '2015-01-01 08:08:08', '2015-02-01 08:08:09', 'month', 'ceil', 2 ), // 1 month and 1 second ahead, should only return 1 month as this function estimates the number of completed periods between
			array( '2015-01-01 08:08:08', '2015-02-01 08:08:09', 'month', 'floor', 1 ),
			array( '2015-01-01 08:08:08', '2015-02-01 08:08:08', 'month', 'ceil', 1 ), // 1 month difference, check how many months
			array( '2015-01-01 08:08:08', '2015-02-01 08:08:08', 'month', 'floor', 1 ),
			array( '2015-01-01 08:08:08', '2015-01-02 08:08:08', 'month', 'ceil', 1 ), // 1 day difference, check how many months between the two dates
			array( '2015-01-01 08:08:08', '2015-01-02 08:08:08', 'month', 'floor', 0 ),

			array( '2016-02-29', '2017-02-28', 'month', 'ceil', 12 ), // leap year test
			array( '2016-02-29', '2017-02-28', 'month', 'floor', 12 ),
			array( '2012-01-10', '2012-09-20', 'month', 'ceil', 9 ), // just over 8 months
			array( '2012-01-10', '2012-09-20', 'month', 'floor', 8 ),

			// how many years between tests
			array( '2014-07-07 12:12:12', '2015-07-07 12:12:12', 'year', 'ceil', 1 ), // just under 1 year apart
			array( '2014-07-07 12:12:12', '2015-07-07 12:12:12', 'year', 'floor', 1 ),
			array( '2014-07-07 12:12:12', '2015-07-07 12:11:12', 'year', 'ceil', 1 ), // just under 1 year apart
			array( '2018-07-07 12:12:12', '2019-07-06 12:12:12', 'year', 'floor', 0 ),
			array( '2013-07-13 10:47:58', '2014-07-13 10:47:59', 'year', 'ceil', 2 ), // just over 1 year
			array( '2012-07-13 10:47:58', '2013-08-13 10:47:59', 'year', 'floor', 1 ),

			array( '2010-01-20', '2015-08-07', 'year', 'ceil', 6 ), // 20 Jan 2010 to 7 AUG 2015
			array( '2010-01-20', '2015-08-07', 'year', 'floor', 5 ),


		);
	}

	/**
	 * Testing wcs_estimate_periods_between()
	 *
	 * @dataProvider estimate_periods_between_data
	 * @since 2.0
	 */
	public function test_estimate_periods_between( $start, $end, $unit_of_time, $rounding_method, $expected ) {
		$this->assertEquals( $expected, wcs_estimate_periods_between( strtotime( $start ), strtotime( $end ), $unit_of_time, $rounding_method ) );
	}


	public function estimate_period_between_data() {
		return array(
			// date, second date, interval, expected (year, month, week, day)

			// difference close to a year
			array( '2011-01-01 01:01:01', '2012-01-01 01:01:02', 1, 'year' ), // 1 year and 1 second difference between the two, 1 interval
			array( '2011-01-01 01:01:01', '2012-01-01 01:01:01', 1, 'year' ), // 1 year, 1 interval
			array( '2011-03-01 01:01:01', '2012-03-01 01:01:01', 1, 'year' ), // 1 year, including leap year
			array( '2012-01-01 01:01:01', '2017-01-01 01:01:01', 5, 'year' ), // 5 years, including 2 leap years
			array( '2011-01-01 01:01:01', '2013-01-01 01:01:02', 2, 'year' ), // 2 years and 1 second, 2 interval
			array( '2011-01-01 01:01:01', '2013-01-01 01:01:01', 2, 'year' ), // 2 years and 1 second, 2 interval
			array( '2011-01-01 01:01:01', '2013-01-01 01:01:00', 2, 'year' ), // a second short of 2 years, 2 interval
			array( '2011-01-01 01:01:01', '2012-12-31 01:01:00', 2, 'year' ), // a day short of 2 years, 2 interval
			array( '2011-01-01 01:01:01', '2012-02-01 01:01:02', 13, 'month' ), // 1 year and 1 month, 13 interval
			array( '2011-01-01 01:01:01', '2012-02-01 01:01:02', 1, 'month' ), // 1 year and 1 month, 1 interval
			array( '2011-01-01 01:01:01', '2011-12-01 01:01:02', 11, 'month' ), // 11 months, 11 interval
			array( '2011-01-01 01:01:01', '2011-12-01 01:01:02', 1, 'month' ), // 11 months, 1 interval
			array( '2011-01-01 01:01:01', '2011-12-24 01:01:01', 51, 'week' ), // 51 weeks, 51 interval
			array( '2011-01-01 01:01:01', '2011-12-24 01:01:01', 1, 'week' ), // 51 weeks, 1 interval
			array( '2011-01-01 01:01:01', '2011-12-27 01:01:01', 1, 'day' ), // 51 weeks and 3 days exactly

			// difference close to a month
			array( '2011-01-01 01:01:01', '2011-02-01 01:01:01', 1, 'month' ), // 1 month, 1 interval
			array( '2011-01-01 01:01:01', '2011-02-01 01:01:02', 1, 'month' ), // 1 month 1 second, 1 interval
			array( '2011-01-01 01:01:01', '2011-09-01 01:01:02', 8, 'month' ), // 8 months 1 second, 1 interval
			array( '2011-01-01 01:01:01', '2011-09-01 01:01:02', 1, 'month' ), // 8 months 1 second, 1 interval
			array( '2011-01-01 01:01:01', '2011-02-03 13:01:02', 1, 'month' ), // 1 month and a few days
			array( '2011-01-01 01:01:01', '2011-02-05 01:01:02', 1, 'week' ), // 5 weeks
			array( '2011-01-01 01:01:01', '2011-01-31 23:01:02', 1, 'week' ), // almost a month
			array( '2011-01-01 01:01:01', '2011-01-30 23:01:02', 1, 'week' ), // 1 day to a month
			array( '2011-01-01 01:01:01', '2011-01-29 23:01:02', 1, 'week' ), // 2 days to a month (this time it's closer to a week)

			// difference close to a week
			array( '2015-01-01 01:01:01', '2015-01-08 01:01:02', 1, 'week' ),
			array( '2015-01-01 01:01:01', '2015-01-07 23:01:02', 1, 'day' ), // because less than a week
			array( '2015-01-01 01:01:01', '2015-01-08 23:01:02', 1, 'week' ),
			array( '2015-01-01 01:01:01', '2015-01-09 01:01:02', 1, 'day' ),
			array( '2015-01-01 01:01:01', '2015-01-15 01:01:02', 2, 'week' ),
			array( '2015-01-01 01:01:01', '2015-01-15 01:01:02', 1, 'week' ),
			array( '2015-01-01 01:01:01', '2015-01-29 01:01:01', 4, 'week' ),
			array( '2015-01-01 01:01:01', '2015-01-31 01:01:01', 4, 'week' ),
			array( '2015-01-01 01:01:01', '2015-01-31 15:01:01', 4, 'week' ),
			array( '2015-01-01 01:01:01', '2015-01-31 23:01:01', 4, 'week' ),
			array( '2015-01-01 01:01:01', '2015-02-01 23:01:01', 4, 'week' ),
			array( '2015-01-01 01:01:01', '2015-02-04 23:01:01', 4, 'week' ),
			array( '2015-01-01 01:01:01', '2015-02-01 23:01:01', 1, 'month' ),
			array( '2015-01-01 01:01:01', '2015-02-12 01:01:01', 3, 'week' ),

			// difference close to a day
			array( '2015-01-01 01:01:01', '2015-01-02 01:01:01', 1, 'day' ),
			array( '2015-01-01 01:01:01', '2015-01-04 01:01:01', 3, 'day' ),
			array( '2015-01-01 01:01:01', '2015-01-08 01:01:01', 7, 'day' ),
			array( '2015-01-01 01:01:01', '2015-01-08 01:01:01', 1, 'week' ),
			array( '2015-01-01 01:01:01', '2015-02-01 01:01:01', 31, 'day' ),
			array( '2015-01-01 01:01:01', '2015-02-01 01:01:01', 1, 'month' ),
			array( '2015-01-01 01:01:01', '2015-01-15 01:01:01', 14, 'day' ),
			array( '2015-01-01 01:01:01', '2015-01-15 01:01:01', 1, 'week' ),
			array( '2015-01-01 01:01:01', '2015-02-15 01:01:01', 1, 'day' ),
			array( '2015-01-01 01:01:01', '2015-02-12 01:01:01', 42, 'day' ),
			array( '2015-01-01 01:01:01', '2015-02-15 01:01:01', 14, 'day' ), // because every other option is less than one block of 14 * period
		);
	}

	/**
	 * Testing wcs_estimate_periods_between()
	 *
	 * @dataProvider estimate_period_between_data
	 * @group estperiod
	 * @since 2.0
	 */
	public function test_estimate_period_between( $start, $end, $interval, $expected ) {
		$this->assertEquals( $expected, wcs_estimate_period_between( $start, $end, $interval ) );
	}

	public function test_wcs_is_datetime_mysql_format() {
		$this->assertTrue( wcs_is_datetime_mysql_format( '2015-10-01 22:18:09' ) );
		$this->assertTrue( wcs_is_datetime_mysql_format( '1970-01-01 00:00:00' ) );
		$this->assertTrue( wcs_is_datetime_mysql_format( '1969-01-01 00:00:00' ) );
	}

	/**
	 * @dataProvider wcs_is_datetime_mysql_format_false_provider
	 */
	public function test_wcs_is_datetime_mysql_format_false( $input ) {
		$this->assertFalse( wcs_is_datetime_mysql_format( $input ) );
	}

	public function wcs_is_datetime_mysql_format_false_provider() {
		return array(
			array( '' ),
			array( 'foo' ),
			array( '2015:12:32 11:11:11' ),
			array( '2015-13-34 24:11:92' ),
			array( '0000-00-00 00:00:00' ),
			array( 4 ),
			array( -1 ),
			array( (float) 34.56 ),
			array( array( 'foo' ) ),
			array( array() ),
			array( true ),
			array( false ),
			array( new stdClass ),
			array( new WP_Error( 'foo' ) ),
			array( null ),
		);
	}

	/**
	 * @dataProvider wcs_number_of_leap_days_provider
	 */
	public function test_wcs_number_of_leap_days( $start_time, $end_time, $expected ) {
		$this->assertEquals( $expected, wcs_number_of_leap_days( $start_time, $end_time ) );
	}

	/**
	 * @dataProvider wcs_number_of_leap_days_error_provider
	 * @expectedException InvalidArgumentException
	 */
	public function test_wcs_number_of_leap_days_error( $start_time, $end_time ) {
		wcs_number_of_leap_days( $start_time, $end_time );
	}

	public function test_wcs_number_of_leap_days_tz_mess() {
		$start_timestamp = strtotime( '1st January 2000' );
		$end_timestamp   = strtotime( '29th February 2016' );

		$this->assertEquals( 5, wcs_number_of_leap_days( $start_timestamp, $end_timestamp ) );

		date_default_timezone_set( 'America/Los_Angeles' );
		$this->assertEquals( 5, wcs_number_of_leap_days( $start_timestamp, $end_timestamp ) );

		date_default_timezone_set( 'UTC' );
		$this->assertEquals( 5, wcs_number_of_leap_days( $start_timestamp, $end_timestamp ) );

		date_default_timezone_set( 'Arctic/Longyearbyen' );
		$this->assertEquals( 5, wcs_number_of_leap_days( $start_timestamp, $end_timestamp ) );

		date_default_timezone_set( 'Pacific/Kiritimati' );
		$this->assertEquals( 5, wcs_number_of_leap_days( $start_timestamp, $end_timestamp ) );

		date_default_timezone_set( 'UTC' );
	}

	public function wcs_number_of_leap_days_error_provider() {
		return array(
			array( '', '' ),
			array( '1st January 2000', 1453141467 ),
			array( 1453141467, '2015-11-13 09:59:00' ),
			array( 1453141467, array() ),
			array( 1453141467, array( 'foo' ) ),
			array( 1453141467, true ),
			array( 1453141467, false ),
			array( 1453141467, new stdClass() ),
			array( 1453141467, new WP_Error( 'foo' ) ),
			array( 1453141467, null ),
		);
	}

	public function wcs_number_of_leap_days_provider() {
		return array(
			array( mktime( 1, 1, 1, 1, 1, 2012 ), mktime( 1, 1, 1, 1, 1, 2013 ), 1 ), // envelope one
			array( mktime( 1, 1, 1, 1, 1, 2015 ), mktime( 1, 1, 1, 4, 1, 2015 ), 0 ), // not in range, no leap year
			array( mktime( 1, 1, 1, 3, 1, 2012 ), mktime( 1, 1, 1, 1, 1, 2015 ), 0 ), // not in range, 1 leap year
			array( mktime( 1, 1, 1, 3, 1, 2012 ), mktime( 1, 1, 1, 2, 1, 2016 ), 0 ), // not in range, 2 leap years
			array( mktime( 23, 59, 59, 2, 29, 2012 ), mktime( 1, 1, 1, 4, 1, 2012 ), 1 ), // touching upper bound, 1 leap year
			array( mktime( 1, 1, 1, 1, 1, 2011 ), mktime( 0, 0, 0, 2, 29, 2012 ), 1 ), // touching lower bound, 1 leap year
			array( mktime( 1, 1, 1, 1, 1, 2010 ), mktime( 1, 1, 1, 12, 31, 2022 ), 3 ), // envelope several fully
			array( mktime( 23, 59, 59, 2, 29, 2012 ), mktime( 1, 1, 1, 1, 1, 2021 ), 3 ), // touching upper bound, multiple leap years
			array( mktime( 1, 1, 1, 1, 1 ,2007 ), mktime( 0, 0, 0, 2, 29, 2016 ), 3 ), // touching lower bound, multiple leap years
		);
	}

	/**
	 * Test WCS_Report_Cache_Manager::get_large_site_cache_update_timestamp()
	 */
	public function test_get_large_site_cache_update_timestamp() {
		// Skip PHP versions prior to 5.5.10.
		if ( version_compare( PHP_VERSION, '5.5.10', '<' ) ) {
			$this->markTestSkipped( 'PHP 5.5.10 is required to reliably get a timestamp for 4 am via a timezone object' );
		}

		$report_cache_manager = new WCS_Report_Cache_Manager();
		$get_cache_update_timestamp_method = $this->get_accessible_protected_method( $report_cache_manager, 'get_large_site_cache_update_timestamp' );

		// Check all GMT offsets between -12 and +12.
		foreach ( range( -12, 12 ) as $gmt_offset ) {
			update_option( 'gmt_offset', $gmt_offset );

			// Convert the GMT offset into +0100 or -1200 format.
			$is_negative = $gmt_offset < 0;
			$gmt_offset  = abs( $gmt_offset );
			$gmt_offset  = sprintf( '%02d00', $gmt_offset );

			if ( $is_negative ) {
				$gmt_offset = '-' . $gmt_offset;
			} else {
				$gmt_offset = '+' . $gmt_offset;
			}

			// Get the timestamp via the the timezone approach. This is the reliable approach however is only available on PHP 5.5.10+
			$timezone_approach  = new DateTime( '4 am', new DateTimeZone( $gmt_offset ) );
			$expected_timestamp = $timezone_approach->format('U');

			if ( $expected_timestamp <= gmdate( 'U' ) ) {
				$expected_timestamp += DAY_IN_SECONDS;
			}

			$timestamp = $get_cache_update_timestamp_method->invoke( $report_cache_manager );

			$this->assertEquals( $expected_timestamp, $timestamp );
		}
	}
}
