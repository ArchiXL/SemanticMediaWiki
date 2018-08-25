<?php

namespace SMW\Page;

use Html;
use SMW\ApplicationFactory;
use SMW\DataValueFactory;
use SMW\DataValues\ValueFormatters\DataValueFormatter;
use SMW\DIProperty;
use SMW\Message;
use SMW\Page\ListBuilder\ListBuilder;
use SMW\Page\ListBuilder\ValueListBuilder;
use SMW\PropertyRegistry;
use SMW\PropertySpecificationReqMsgBuilder;
use SMW\RequestOptions;
use SMW\Store;
use SMW\StringCondition;
use Title;
use SMW\Utils\HtmlTabs;

/**
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class PropertyPage extends Page {

	/**
	 * @var Store
	 */
	private $store;

	/**
	 * @var PropertySpecificationReqMsgBuilder
	 */
	private $propertySpecificationReqMsgBuilder;

	/**
	 * @var DIProperty
	 */
	private $property;

	/**
	 * @var DataValue
	 */
	private $propertyValue;

	/**
	 * @var ListBuilder
	 */
	private $listBuilder;

	/**
	 * @see 3.0
	 *
	 * @param Title $title
	 * @param Store $store
	 * @param PropertySpecificationReqMsgBuilder $propertySpecificationReqMsgBuilder
	 */
	public function __construct( Title $title, Store $store, PropertySpecificationReqMsgBuilder $propertySpecificationReqMsgBuilder ) {
		parent::__construct( $title );
		$this->store = $store;
		$this->propertySpecificationReqMsgBuilder = $propertySpecificationReqMsgBuilder;
	}

	/**
	 * @see Page::initParameters()
	 */
	protected function initParameters() {
		// We use a smaller limit here; property pages might become large
		$this->limit = $this->getOption( 'pagingLimit' );
		$this->property = DIProperty::newFromUserLabel( $this->getTitle()->getText() );
		$this->propertyValue = DataValueFactory::getInstance()->newDataValueByItem( $this->property );
	}

	/**
	 * @see Page::getIntroductoryText
	 *
	 * @since 3.0
	 *
	 * @return string
	 */
	protected function getIntroductoryText() {

		$redirectTarget = $this->store->getRedirectTarget( $this->property );

		if ( !$redirectTarget->equals( $this->property ) ) {
			return '';
		}

		$this->propertySpecificationReqMsgBuilder->setSemanticData(
			$this->fetchSemanticDataFromEditInfo()
		);

		$this->propertySpecificationReqMsgBuilder->check(
			$this->property
		);

		return $this->propertySpecificationReqMsgBuilder->getMessage();
	}

	/**
	 * @see Page::isLockedView
	 *
	 * @since 3.0
	 *
	 * @return boolean
	 */
	protected function isLockedView() {
		return $this->propertySpecificationReqMsgBuilder->reqLock();
	}

	/**
	 * @see Page::getTopIndicators
	 *
	 * @since 3.0
	 *
	 * @return string
	 */
	protected function getTopIndicators() {

		if ( !$this->store->getRedirectTarget( $this->property )->equals( $this->property ) ) {
			return '';
		}

		// Label that corresponds to the display and sort characteristics
		$searchLabel = $this->property->isUserDefined() ? $this->propertyValue->getSearchLabel() : $this->property->getCanonicalLabel();
		$usageCountHtml = '';

		$requestOptions = new RequestOptions();
		$requestOptions->setLimit( 1 );
		$requestOptions->addStringCondition( $searchLabel, StringCondition::COND_EQ );

		$cachedLookupList = $this->store->getPropertiesSpecial( $requestOptions );
		$usageList = $cachedLookupList->fetchList();

		if ( $usageList && $usageList !== array() ) {

			$usage = end( $usageList );
			$usageCount = $usage[1];
			$date = $this->getContext()->getLanguage()->timeanddate( $cachedLookupList->getTimestamp() );

			$countMsg = Message::get( array( 'smw-property-indicator-last-count-update', $date ) );
			$indicatorClass = ( $usageCount < 25000 ? ( $usageCount > 5000 ? ' moderate' : '' ) : ' high' );

			$usageCountHtml = Html::rawElement(
				'div', array(
					'title' => $countMsg,
					'class' => 'smw-page-indicator usage-count' . $indicatorClass ),
				$usageCount
			);
		}

		$type = Html::rawElement(
				'div',
				array(
					'class' => 'smw-page-indicator property-type',
					'title' => Message::get( array( 'smw-property-indicator-type-info', $this->property->isUserDefined() ), Message::PARSE )
			), ( $this->property->isUserDefined() ? 'U' : 'S' )
		);

		return array(
			'smw-prop-count' => $usageCountHtml,
			'smw-prop-type' => $type
		);
	}

	/**
	 * @see Page::getRedirectTargetURL
	 *
	 * @since 3.0
	 *
	 * @return string|boolean
	 */
	protected function getRedirectTargetURL() {

		$label = $this->getTitle()->getText();

		$property = new DIProperty(
			PropertyRegistry::getInstance()->findPropertyIdByLabel( $label )
		);

		// Ensure to redirect to `Property:Modification date` and not using
		// a possible user contextualized version such as `Property:Date de modification`
		$canonicalLabel = $property->getCanonicalLabel();

		if ( $canonicalLabel !== '' && $label !== $canonicalLabel ) {
			return $property->getCanonicalDiWikiPage()->getTitle()->getFullURL();
		}

		return false;
	}

	/**
	 * Returns the HTML which is added to $wgOut after the article text.
	 *
	 * @return string
	 */
	protected function getHtml() {

		if ( !$this->store->getRedirectTarget( $this->property )->equals( $this->property ) ) {
			return '';
		}

		$context = $this->getContext();
		$html = '';
		$languageCode = $context->getLanguage()->getCode();

		$context->getOutput()->addModuleStyles( 'ext.smw.page.styles' );
		$context->getOutput()->addModules( 'smw.property.page' );

		$context->getOutput()->setPageTitle(
			$this->propertyValue->getFormattedLabel( DataValueFormatter::WIKI_LONG )
		);

		$this->listBuilder = new ListBuilder(
			$this->store
		);

		$this->listBuilder->setLanguageCode(
			$languageCode
		);

		$this->listBuilder->isUserDefined(
			$this->property->isUserDefined()
		);

		$htmlTabs = new HtmlTabs();
		$htmlTabs->setGroup( 'property' );

		$html = $this->makeValueList( $languageCode );

		$htmlTabs->tab( 'smw-property-value', $this->msg( 'smw-property-tab-usage' ), [ 'hide' => $html === '' ] );
		$htmlTabs->content( 'smw-property-value', $html );

		// Redirects
		$html = $this->makeList( 'redirect', '_REDI', true );

		$htmlTabs->tab( 'smw-property-redi', $this->msg( 'smw-property-tab-redirects' ), [ 'hide' => $html === '' ] );
		$htmlTabs->content( 'smw-property-redi', $html );

		// Subproperties
		$html = $this->makeList( 'subproperty', '_SUBP', true );

		$htmlTabs->tab( 'smw-property-subp', $this->msg( 'smw-property-tab-subproperties' ),  [ 'hide' => $html === '' ] );
		$htmlTabs->content( 'smw-property-subp', $html );

		// Improperty values
		$html = $this->makeList( 'error', '_ERRP', false );

		$htmlTabs->tab( 'smw-property-errp', $this->msg( 'smw-property-tab-errors' ),  [ 'hide' => $html === '', 'class' => 'smw-tab-warning' ] );
		$htmlTabs->content( 'smw-property-errp', $html );

		$html = $htmlTabs->buildHTML(
			[ 'class' => 'smw-property clearfix' ]
		);

		return $html;
	}

	private function fetchSemanticDataFromEditInfo() {

		$applicationFactory = ApplicationFactory::getInstance();

		if ( $this->getPage()->getRevision() === null ) {
			return null;
		}

		$editInfoProvider = $applicationFactory->newMwCollaboratorFactory()->newEditInfoProvider(
			$this->getPage(),
			$this->getPage()->getRevision()
		);

		return $editInfoProvider->fetchSemanticData();
	}

	private function makeList( $key, $propertyKey, $checkProperty = true ) {

		// Ignore the list when a filter is present
		if ( $this->getContext()->getRequest()->getVal( 'filter', '' ) !== '' ) {
			return '';
		}

		$propertyListLimit = $this->getOption( 'smwgPropertyListLimit' );
		$listLimit = $propertyListLimit[$key];

		$requestOptions = new RequestOptions();
		$requestOptions->sort = true;
		$requestOptions->ascending = true;

		// +1 look ahead
		$requestOptions->setLimit(
			$listLimit + 1
		);

		$this->listBuilder->setListLimit(
			$listLimit
		);

		$this->listBuilder->setListHeader(
			'smw-propertylist-' . $key
		);

		$this->listBuilder->checkProperty(
			$checkProperty
		);

		return $this->listBuilder->createHtml(
			new DIProperty( $propertyKey ),
			$this->getDataItem(),
			$requestOptions
		);
	}

	private function makeValueList( $languageCode ) {

		$request = $this->getContext()->getRequest();

		$valueListBuilder = new ValueListBuilder(
			$this->store
		);

		$valueListBuilder->setLanguageCode(
			$languageCode
		);

		$valueListBuilder->setPagingLimit(
			$this->getOption( 'pagingLimit' )
		);

		$valueListBuilder->setMaxPropertyValues(
			$this->getOption( 'smwgMaxPropertyValues' )
		);

		return $valueListBuilder->createHtml(
			$this->property,
			$this->getDataItem(),
			[
				'limit'  => $request->getVal( 'limit', $this->getOption( 'pagingLimit' ) ),
				'offset' => $request->getVal( 'offset', '0' ),
				'from'   => $request->getVal( 'from', '' ),
				'until'  => $request->getVal( 'until', '' ),
				'filter' => $request->getVal( 'filter', '' )
			]
		);
	}

	private function msg( $params, $type = Message::TEXT, $lang = Message::USER_LANGUAGE ) {
		return Message::get( $params, $type, $lang );
	}

}