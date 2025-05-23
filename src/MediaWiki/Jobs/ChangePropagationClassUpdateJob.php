<?php

namespace SMW\MediaWiki\Jobs;

use Title;

/**
 * Isolate instance to count update jobs in connection with a category related
 * update.
 *
 * @license GPL-2.0-or-later
 * @since 3.0
 *
 * @author mwjames
 */
class ChangePropagationClassUpdateJob extends ChangePropagationUpdateJob {

	/**
	 * Identifies the job queue command
	 */
	const JOB_COMMAND = 'smw.changePropagationClassUpdate';

	/**
	 * @since 3.0
	 *
	 * @param Title $title
	 * @param array $params job parameters
	 */
	public function __construct( Title $title, $params = [] ) {
		$params = array_merge(
			$params,
			[ 'origin' => 'ChangePropagationClassUpdateJob' ]
		);

		parent::__construct( $title, $params, self::JOB_COMMAND );
	}

}
