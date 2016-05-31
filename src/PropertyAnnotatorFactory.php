<?php

namespace SMW;

use SMw\MediaWiki\RedirectTargetFinder;
use SMW\PropertyAnnotator\CategoryPropertyAnnotator;
use SMW\PropertyAnnotator\DisplayTitlePropertyAnnotator;
use SMW\PropertyAnnotator\MandatoryTypePropertyAnnotator;
use SMW\PropertyAnnotator\NullPropertyAnnotator;
use SMW\PropertyAnnotator\PredefinedPropertyAnnotator;
use SMW\PropertyAnnotator\RedirectPropertyAnnotator;
use SMW\PropertyAnnotator\SortKeyPropertyAnnotator;
use SMW\Store;

/**
 * @license GNU GPL v2+
 * @since 2.0
 *
 * @author mwjames
 */
class PropertyAnnotatorFactory {

	/**
	 * @since 2.0
	 *
	 * @param SemanticData $semanticData
	 *
	 * @return NullPropertyAnnotator
	 */
	public function newNullPropertyAnnotator( SemanticData $semanticData ) {
		return new NullPropertyAnnotator( $semanticData );
	}

	/**
	 * @since 2.0
	 *
	 * @param SemanticData $semanticData
	 * @param RedirectTargetFinder $redirectTargetFinder
	 *
	 * @return RedirectPropertyAnnotator
	 */
	public function newRedirectPropertyAnnotator( PropertyAnnotator $propertyAnnotator, RedirectTargetFinder $redirectTargetFinder ) {
		return new RedirectPropertyAnnotator(
			$propertyAnnotator,
			$redirectTargetFinder
		);
	}

	/**
	 * @since 2.0
	 *
	 * @param SemanticData $semanticData
	 * @param PageInfo $pageInfo
	 *
	 * @return PredefinedPropertyAnnotator
	 */
	public function newPredefinedPropertyAnnotator( PropertyAnnotator $propertyAnnotator, PageInfo $pageInfo ) {

		$predefinedPropertyAnnotator = new PredefinedPropertyAnnotator(
			$propertyAnnotator,
			$pageInfo
		);

		$predefinedPropertyAnnotator->setPredefinedPropertyList(
			ApplicationFactory::getInstance()->getSettings()->get( 'smwgPageSpecialProperties' )
		);

		return $predefinedPropertyAnnotator;
	}

	/**
	 * @since 2.0
	 *
	 * @param SemanticData $semanticData
	 * @param string $sortkey
	 *
	 * @return SortKeyPropertyAnnotator
	 */
	public function newSortKeyPropertyAnnotator( PropertyAnnotator $propertyAnnotator, $sortkey ) {
		return new SortKeyPropertyAnnotator(
			$propertyAnnotator,
			$sortkey
		);
	}

	/**
	 * @since 2.4
	 *
	 * @param SemanticData $semanticData
	 * @param string|false $displayTitle
	 * @param string $defaultSort
	 *
	 * @return DisplayTitlePropertyAnnotator
	 */
	public function newDisplayTitlePropertyAnnotator( PropertyAnnotator $propertyAnnotator, $displayTitle, $defaultSort ) {

		$displayTitlePropertyAnnotator = new DisplayTitlePropertyAnnotator(
			$propertyAnnotator,
			$displayTitle,
			$defaultSort
		);

		$displayTitlePropertyAnnotator->canCreateAnnotation(
			( ApplicationFactory::getInstance()->getSettings()->get( 'smwgDVFeatures' ) & SMW_DV_WPV_DTITLE ) != 0
		);

		return $displayTitlePropertyAnnotator;
	}

	/**
	 * @since 2.0
	 *
	 * @param SemanticData $semanticData
	 * @param array $categories
	 *
	 * @return CategoryPropertyAnnotator
	 */
	public function newCategoryPropertyAnnotator( PropertyAnnotator $propertyAnnotator, array $categories ) {

		$categoryPropertyAnnotator = new CategoryPropertyAnnotator(
			$propertyAnnotator,
			$categories
		);

		$categoryPropertyAnnotator->setShowHiddenCategoriesState(
			ApplicationFactory::getInstance()->getSettings()->get( 'smwgShowHiddenCategories' )
		);

		$categoryPropertyAnnotator->setCategoryInstanceUsageState(
			ApplicationFactory::getInstance()->getSettings()->get( 'smwgCategoriesAsInstances' )
		);

		$categoryPropertyAnnotator->setCategoryHierarchyUsageState(
			ApplicationFactory::getInstance()->getSettings()->get( 'smwgUseCategoryHierarchy' )
		);

		return $categoryPropertyAnnotator;
	}

	/**
	 * @since 2.2
	 *
	 * @param SemanticData $semanticData
	 *
	 * @return MandatoryTypePropertyAnnotator
	 */
	public function newMandatoryTypePropertyAnnotator( PropertyAnnotator $propertyAnnotator ) {
		return new MandatoryTypePropertyAnnotator(
			$propertyAnnotator
		);
	}

}
