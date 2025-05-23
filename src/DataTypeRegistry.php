<?php

namespace SMW;

use MediaWiki\MediaWikiServices;
use RuntimeException;
use SMW\Localizer\Localizer;
use SMW\Localizer\LocalLanguage\LocalLanguage;
use SMWDataItem as DataItem;

/**
 * DataTypes registry class
 *
 * Registry class that manages datatypes, and provides various methods to access
 * the information
 *
 * @license GPL-2.0-or-later
 * @since 1.9
 *
 * @author Markus Krötzsch
 * @author Jeroen De Dauw
 * @author mwjames
 */
class DataTypeRegistry {

	/**
	 * @var DataTypeRegistry
	 */
	protected static $instance = null;

	private LocalLanguage $localLanguage;

	/**
	 * Array of type labels indexed by type ids. Used for datatype resolution.
	 *
	 * @var string[]
	 */
	private $typeLabels = [];

	/**
	 * Array of ids indexed by type aliases. Used for datatype resolution.
	 *
	 * @var string[]
	 */
	private $typeAliases = [];

	/**
	 * @var string[]
	 */
	private $canonicalLabels = [];

	/**
	 * Array of class names for creating new SMWDataValue, indexed by type
	 * id.
	 *
	 * @var string[]
	 */
	private $typeClasses;

	/**
	 * Array of data item classes, indexed by type id.
	 *
	 * @var int[]
	 */
	private $typeDataItemIds;

	/**
	 * @var string[]
	 */
	private $subTypes = [];

	/**
	 * @var
	 */
	private $browsableTypes = [];

	/**
	 * Lookup map that allows finding a datatype id given a label or alias.
	 * All labels and aliases (ie array keys) are stored lower case.
	 *
	 * @var string[]
	 */
	private $typeByLabelOrAliasLookup = [];

	/**
	 * Array of default types to use for making datavalues for dataitems.
	 *
	 * @var string[]
	 */
	private $defaultDataItemTypeMap = [
		DataItem::TYPE_BLOB => '_txt', // Text type
		DataItem::TYPE_URI => '_uri', // URL/URI type
		DataItem::TYPE_WIKIPAGE => '_wpg', // Page type
		DataItem::TYPE_NUMBER => '_num', // Number type
		DataItem::TYPE_TIME => '_dat', // Time type
		DataItem::TYPE_BOOLEAN => '_boo', // Boolean type
		DataItem::TYPE_CONTAINER => '_rec', // Value list type (replacing former nary properties)
		DataItem::TYPE_GEO => '_geo', // Geographical coordinates
		DataItem::TYPE_CONCEPT => '__con', // Special concept page type
		DataItem::TYPE_PROPERTY => '__pro', // Property type

		// If either of the following two occurs, we want to see a PHP error:
		//DataItem::TYPE_NOTYPE => '',
		//DataItem::TYPE_ERROR => '',
	];

	/**
	 * @var
	 */
	private $callables = [];

	/**
	 * Returns a DataTypeRegistry instance
	 *
	 * @since 1.9
	 *
	 * @return DataTypeRegistry
	 */
	public static function getInstance() {
		if ( self::$instance !== null ) {
			return self::$instance;
		}

		self::$instance = new self(
			Localizer::getInstance()->getLang()
		);

		self::$instance->initDatatypes(
			TypesRegistry::getDataTypeList()
		);

		return self::$instance;
	}

	/**
	 * Resets the DataTypeRegistry instance
	 *
	 * @since 1.9
	 */
	public static function clear() {
		self::$instance = null;
	}

	/**
	 * @since 1.9.0.2
	 *
	 * @param Lang $localLanguage
	 */
	public function __construct( LocalLanguage $localLanguage ) {
		$this->localLanguage = $localLanguage;
		$this->registerLabels();
	}

	/**
	 * @deprecated since 2.5, use DataTypeRegistry::getDataItemByType
	 */
	public function getDataItemId( $typeId ) {
		return $this->getDataItemByType( $typeId );
	}

	/**
	 * Get the preferred data item ID for a given type. The ID defines the
	 * appropriate data item class for processing data of this type. See
	 * DataItem for possible values.
	 *
	 * @note SMWDIContainer is a pseudo dataitem type that is used only in
	 * data input methods, but not for storing data. Types that work with
	 * SMWDIContainer use SMWDIWikiPage as their DI type. (Since SMW 1.8)
	 *
	 * @param $typeId string id string for the given type
	 *
	 * @return int data item ID
	 */
	public function getDataItemByType( $typeId ) {
		if ( isset( $this->typeDataItemIds[$typeId] ) ) {
			return $this->typeDataItemIds[$typeId];
		}

		return DataItem::TYPE_NOTYPE;
	}

	/**
	 * @since  2.0
	 *
	 * @param string
	 *
	 * @return bool
	 */
	public function isRegistered( $typeId ) {
		return isset( $this->typeDataItemIds[$typeId] );
	}

	/**
	 * @since 3.2
	 *
	 * @param string $typeId
	 *
	 * @return bool
	 */
	public function isRecordType( string $typeId ): bool {
		return strpos( $typeId, '_rec' ) !== false;
	}

	/**
	 * @since 2.4
	 *
	 * @param string $typeId
	 *
	 * @return bool
	 */
	public function isSubDataType( $typeId ) {
		return isset( $this->subTypes[$typeId] ) && $this->subTypes[$typeId];
	}

	/**
	 * @since 3.0
	 *
	 * @param string $typeId
	 *
	 * @return bool
	 */
	public function isBrowsableType( $typeId ) {
		return isset( $this->browsableTypes[$typeId] ) && $this->browsableTypes[$typeId];
	}

	/**
	 * @since 2.5
	 *
	 * @param string $srcType
	 * @param string $tagType
	 *
	 * @return bool
	 */
	public function isEqualByType( $srcType, $tagType ) {
		return $this->getDataItemByType( $srcType ) === $this->getDataItemByType( $tagType );
	}

	/**
	 * A function for registering/overwriting datatypes for SMW. Should be
	 * called from within the hook 'smwInitDatatypes'.
	 *
	 * @param $id string type ID for which this datatype is registered
	 * @param $className string name of the according subclass of SMWDataValue
	 * @param $dataItemId integer ID of the data item class that this data value uses, see DataItem
	 * @param $label mixed string label or false for types that cannot be accessed by users
	 * @param bool $isSubDataType
	 * @param bool $isBrowsableType
	 */
	public function registerDataType( $id, $className, $dataItemId, $label = false, $isSubDataType = false, $isBrowsableType = false ) {
		$this->typeClasses[$id] = $className;
		$this->typeDataItemIds[$id] = $dataItemId;
		$this->subTypes[$id] = $isSubDataType;
		$this->browsableTypes[$id] = $isBrowsableType;

		if ( $label !== false ) {
			$this->registerTypeLabel( $id, $label );
		}
	}

	private function registerTypeLabel( $typeId, $typeLabel ) {
		$this->typeLabels[$typeId] = $typeLabel;
		$this->addTextToIdLookupMap( $typeId, $typeLabel );
	}

	private function addTextToIdLookupMap( $dataTypeId, $text ) {
		$this->typeByLabelOrAliasLookup[mb_strtolower( $text )] = $dataTypeId;
	}

	/**
	 * Add a new alias label to an existing datatype id. Note that every ID
	 * should have a primary label, either provided by SMW or registered with
	 * registerDataType(). This function should be called from within the hook
	 * 'smwInitDatatypes'.
	 *
	 * @param string $typeId
	 * @param string $typeAlias
	 */
	public function registerDataTypeAlias( $typeId, $typeAlias ) {
		$this->typeAliases[$typeAlias] = $typeId;
		$this->addTextToIdLookupMap( $typeId, $typeAlias );
	}

	/**
	 * @deprecated since 3.0, use DataTypeRegistry::findTypeByLabel
	 */
	public function findTypeId( $label ) {
		return $this->findTypeByLabel( $label );
	}

	/**
	 * Look up the ID that identifies the datatype of the given label
	 * internally. This id is used for all internal operations. If the
	 * label does not belong to a known type, the empty string is returned.
	 *
	 * @since 3.0
	 *
	 * @param string $label
	 *
	 * @return string
	 */
	public function findTypeByLabel( $label ) {
		$label = mb_strtolower( $label );

		if ( isset( $this->typeByLabelOrAliasLookup[$label] ) ) {
			return $this->typeByLabelOrAliasLookup[$label];
		}

		return '';
	}

	/**
	 * @since 2.5
	 *
	 * @param string $label
	 * @param string|false $languageCode
	 *
	 * @return string
	 */
	public function findTypeByLabelAndLanguage( $label, $languageCode = false ) {
		if ( !$languageCode ) {
			return $this->findTypeByLabel( $label );
		}

		$lang = $this->localLanguage->fetch(
			$languageCode
		);

		$type = $lang->findDatatypeByLabel( $label );

		if ( $type === '' ) {
			$type = $this->findTypeByLabel( $label );
		}

		return $type;
	}

	/**
	 * @since 3.0
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public function getFieldType( $type ) {
		if ( isset( $this->typeDataItemIds[$type] ) ) {
			return $this->defaultDataItemTypeMap[$this->typeDataItemIds[$type]];
		}

		return '_wpg';
	}

	/**
	 * Get the translated user label for a given internal ID. If the ID does
	 * not have a label associated with it in the current language, the
	 * empty string is returned. This is the case both for internal type ids
	 * and for invalid (unknown) type ids, so this method cannot be used to
	 * distinguish the two.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	public function findTypeLabel( $id ) {
		if ( isset( $this->typeLabels[$id] ) ) {
			return $this->typeLabels[$id];
		}

		// internal type without translation to user space;
		// might also happen for historic types after an upgrade --
		// alas, we have no idea what the former label would have been
		return '';
	}

	/**
	 * Returns a label for a typeId that is independent from the user/content
	 * language
	 *
	 * @since 2.3
	 *
	 * @return string
	 */
	public function findCanonicalLabelById( $id ) {
		if ( isset( $this->canonicalLabels[$id] ) ) {
			return $this->canonicalLabels[$id];
		}

		return '';
	}

	/**
	 * @since 2.4
	 *
	 * @return array
	 */
	public function getCanonicalDatatypeLabels() {
		return $this->canonicalLabels;
	}

	/**
	 * Return an array of all labels that a user might specify as the type of
	 * a property, and that are internal (i.e. not user defined). No labels are
	 * returned for internal types without user labels (e.g. the special types
	 * for some special properties), and for user defined types.
	 *
	 * @return array
	 */
	public function getKnownTypeLabels() {
		return $this->typeLabels;
	}

	/**
	 * @since 2.1
	 *
	 * @return array
	 */
	public function getKnownTypeAliases() {
		return $this->typeAliases;
	}

	/**
	 * @deprecated since 2.5, use DataTypeRegistry::getDefaultDataItemByType
	 */
	public function getDefaultDataItemTypeId( $diType ) {
		return $this->getDefaultDataItemByType( $diType );
	}

	/**
	 * Returns a default DataItem for a matchable type ID
	 *
	 * @since 2.5
	 *
	 * @param string $typeId
	 *
	 * @return string|null
	 */
	public function getDefaultDataItemByType( $typeId ) {
		if ( isset( $this->defaultDataItemTypeMap[$typeId] ) ) {
			return $this->defaultDataItemTypeMap[$typeId];
		}

		return null;
	}

	/**
	 * Returns a class based on a typeId
	 *
	 * @since 1.9
	 *
	 * @param string $typeId
	 *
	 * @return string|null
	 */
	public function getDataTypeClassById( $typeId ) {
		if ( $this->hasDataTypeClassById( $typeId ) ) {
			return $this->typeClasses[$typeId];
		}

		return null;
	}

	/**
	 * Whether a datatype class is registered for a particular typeId
	 *
	 * @since 1.9
	 *
	 * @param string $typeId
	 *
	 * @return bool
	 */
	public function hasDataTypeClassById( $typeId ) {
		if ( !isset( $this->typeClasses[$typeId] ) ) {
			return false;
		}

		if ( is_callable( $this->typeClasses[$typeId] ) ) {
			return true;
		}

		return class_exists( $this->typeClasses[$typeId] );
	}

	/**
	 * Gather all available datatypes and label<=>id<=>datatype
	 * associations. This method is called before most methods of this
	 * factory.
	 */
	protected function initDatatypes( array $typeList ) {
		foreach ( $typeList as $id => $definition ) {

			if ( isset( $definition[0] ) ) {
				$this->typeClasses[$id] = $definition[0];
			}

			$this->typeDataItemIds[$id] = $definition[1];
			$this->subTypes[$id] = $definition[2];
			$this->browsableTypes[$id] = $definition[3];
		}

		// Deprecated since 1.9
		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		$hookContainer->run( 'smwInitDatatypes' );

		// Since 1.9
		$hookContainer->run( 'SMW::DataType::initTypes' );
	}

	/**
	 * This function allows for registered types to add additional data or functions
	 * required by an individual DataValue of that type.
	 *
	 * Register the data:
	 * $dataTypeRegistry = DataTypeRegistry::getInstance();
	 *
	 * $dataTypeRegistry->registerDataType( '__foo', ... );
	 * $dataTypeRegistry->registerCallable( '__foo', 'my.function', ... );
	 * ...
	 *
	 * Access the data:
	 * $dataValueFactory = DataValueFactory::getInstance();
	 *
	 * $dataValue = $dataValueFactory->newDataValueByType( '__foo' );
	 * $dataValue->getCallable( 'my.function' )
	 * ...
	 *
	 * @since 3.1
	 *
	 * @param string $typeId
	 * @param string $key
	 * @param callable $callable
	 *
	 * @throws RuntimeException
	 */
	public function registerCallable( $typeId, $key, callable $callable ) {
		if ( !is_string( $typeId ) || !is_string( $key ) ) {
			throw new RuntimeException( "`$key`, `$typeId` need to be a string!" );
		}

		if ( isset( $this->callables[$typeId][$key] ) ) {
			throw new RuntimeException( "`$key` is already in use for type `$typeId`!" );
		}

		$this->callables[$typeId][$key] = $callable;
	}

	/**
	 * @since 3.1
	 *
	 * @param string $typeId
	 *
	 * @return
	 */
	public function getCallablesByTypeId( $typeId ) {
		if ( !isset( $this->callables[$typeId] ) ) {
			return [];
		}

		return $this->callables[$typeId];
	}

	/**
	 * @since 3.1
	 */
	public function clearCallables() {
		$this->callables = [];
	}

	private function registerLabels() {
		foreach ( $this->localLanguage->getDatatypeLabels() as $typeId => $typeLabel ) {
			$this->registerTypeLabel( $typeId, $typeLabel );
		}

		foreach ( $this->localLanguage->getDatatypeAliases() as $typeAlias => $typeId ) {
			$this->registerDataTypeAlias( $typeId, $typeAlias );
		}

		foreach ( $this->localLanguage->getCanonicalDatatypeLabels() as $label => $id ) {
			$this->canonicalLabels[$id] = $label;
		}
	}

}
