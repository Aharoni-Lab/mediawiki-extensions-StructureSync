/**
 * StructureSync Hierarchy Visualization
 * --------------------------------------
 * Frontend module for rendering category hierarchy trees and inherited properties.
 * 
 * Usage:
 * - Special:StructureSync/hierarchy tab
 * - Category pages (via parser function)
 * - PageForms preview
 * 
 * API:
 * mw.StructureSyncHierarchy.renderInto(container, categoryTitle)
 */
(function (mw, $) {
	'use strict';

	/**
	 * Render the hierarchy tree as nested lists.
	 * 
	 * @param {jQuery} $container Container element
	 * @param {Object} hierarchyData Hierarchy data from API
	 */
	function renderHierarchyTree($container, hierarchyData) {
		var rootTitle = hierarchyData.rootCategory || null;
		
		if (!rootTitle || !hierarchyData.nodes || !hierarchyData.nodes[rootTitle]) {
			$container.empty().append(
				$('<p>').addClass('ss-hierarchy-empty').text(
					mw.msg('structuresync-hierarchy-no-data')
				)
			);
			return;
		}

		/**
		 * Create a link to a category page.
		 * 
		 * @param {string} title Full category title with "Category:" prefix
		 * @return {jQuery} Link element
		 */
		function makeCategoryLink(title) {
			var href = mw.util.getUrl(title);
			var displayName = title.replace(/^Category:/, '');
			return $('<a>')
				.attr('href', href)
				.attr('title', title)
				.text(displayName);
		}

		/**
		 * Recursively build tree node.
		 * 
		 * @param {string} title Category title
		 * @return {jQuery|null} List item element or null
		 */
		function buildNode(title) {
			var node = hierarchyData.nodes[title];
			if (!node) {
				return null;
			}

			var $li = $('<li>').append(makeCategoryLink(title));

			// If this node has parents, create nested list
			if (Array.isArray(node.parents) && node.parents.length > 0) {
				var $ul = $('<ul>').addClass('ss-hierarchy-tree-nested');
				
				node.parents.forEach(function (parentTitle) {
					var $childNode = buildNode(parentTitle);
					if ($childNode) {
						$ul.append($childNode);
					}
				});
				
				$li.append($ul);
			}

			return $li;
		}

		// Build and render the tree
		var $rootList = $('<ul>').addClass('ss-hierarchy-tree');
		var $rootNode = buildNode(rootTitle);
		
		if ($rootNode) {
			$rootList.append($rootNode);
		}

		$container.empty().append($rootList);
	}

	/**
	 * Render the inherited properties table.
	 * 
	 * Properties are grouped by source category and colored by required/optional status.
	 * 
	 * @param {jQuery} $container Container element
	 * @param {Object} hierarchyData Hierarchy data from API
	 */
	function renderPropertyTable($container, hierarchyData) {
		var props = hierarchyData.inheritedProperties || [];
		
		if (props.length === 0) {
			$container.empty().append(
				$('<p>').addClass('ss-hierarchy-empty').text(
					mw.msg('structuresync-hierarchy-no-properties')
				)
			);
			return;
		}

		// Group properties by source category
		var grouped = {};
		props.forEach(function (p) {
			var source = p.sourceCategory || '';
			if (!grouped[source]) {
				grouped[source] = [];
			}
			grouped[source].push(p);
		});

		// Create table
		var $table = $('<table>')
			.addClass('wikitable ss-prop-table');

		// Table header
		var $thead = $('<thead>').append(
			$('<tr>')
				.append($('<th>').text(mw.msg('structuresync-hierarchy-source-category')))
				.append($('<th>').text(mw.msg('structuresync-hierarchy-properties')))
		);
		$table.append($thead);

		// Table body
		var $tbody = $('<tbody>');

		// Sort source categories for consistent display
		var sources = Object.keys(grouped).sort();

		sources.forEach(function (sourceTitle) {
			var $row = $('<tr>');

			// Category cell
			var $catCell = $('<td>').addClass('ss-prop-source-cell');
			if (sourceTitle) {
				var href = mw.util.getUrl(sourceTitle);
				var displayName = sourceTitle.replace(/^Category:/, '');
				$catCell.append(
					$('<a>')
						.attr('href', href)
						.attr('title', sourceTitle)
						.text(displayName)
				);
			} else {
				$catCell.text(mw.msg('structuresync-hierarchy-unknown-category'));
			}

			// Properties cell
			var $propCell = $('<td>').addClass('ss-prop-list-cell');
			var $ul = $('<ul>').addClass('ss-prop-list');

		grouped[sourceTitle].forEach(function (p) {
			var $li = $('<li>');
			// Check if required (API returns 1 for true, 0 for false, or undefined)
			var isRequired = (p.required === 1 || p.required === true);
			var cssClass = isRequired ? 'ss-prop-required' : 'ss-prop-optional';
			$li.addClass(cssClass);

			var propertyTitle = p.propertyTitle || '';
			if (propertyTitle) {
				var propHref = mw.util.getUrl(propertyTitle);
				var propDisplayName = propertyTitle.replace(/^Property:/, '');
				$li.append(
					$('<a>')
						.attr('href', propHref)
						.attr('title', propertyTitle)
						.text(propDisplayName)
				);
				
				// Add badge for required/optional
				var badgeText = isRequired
					? mw.msg('structuresync-hierarchy-required')
					: mw.msg('structuresync-hierarchy-optional');
					$li.append(
						' ',
						$('<span>')
							.addClass('ss-prop-badge')
							.text(badgeText)
					);
				} else {
					$li.text(mw.msg('structuresync-hierarchy-unnamed-property'));
				}

				$ul.append($li);
			});

			$propCell.append($ul);

			$row.append($catCell).append($propCell);
			$tbody.append($row);
		});

		$table.append($tbody);
		$container.empty().append($table);
	}

	/**
	 * Fetch hierarchy data from API and render it.
	 * 
	 * @param {jQuery} $root Root container element
	 * @param {string} categoryTitle Category title (with or without "Category:" prefix)
	 */
	function fetchAndRender($root, categoryTitle) {
		// Show loading state
		$root.addClass('ss-hierarchy-loading').empty().append(
			$('<p>').text(mw.msg('structuresync-hierarchy-loading'))
		);

		var api = new mw.Api();
		api.get({
			action: 'structuresync-hierarchy',
			category: categoryTitle,
			format: 'json'
		}).done(function (data) {
			$root.removeClass('ss-hierarchy-loading');
			
			var moduleData = data['structuresync-hierarchy'];
			if (!moduleData) {
				$root.empty().append(
					$('<p>').addClass('error').text(
						mw.msg('structuresync-hierarchy-no-data')
					)
				);
				return;
			}

			// Create containers for tree and properties
			var $treeContainer = $('<div>').addClass('ss-hierarchy-tree-container');
			var $propsContainer = $('<div>').addClass('ss-hierarchy-props-container');

			// Build the UI
			$root.empty().append(
				$('<div>').addClass('ss-hierarchy-section').append(
					$('<h3>').text(mw.msg('structuresync-hierarchy-tree-title')),
					$treeContainer
				),
				$('<div>').addClass('ss-hierarchy-section').append(
					$('<h3>').text(mw.msg('structuresync-hierarchy-props-title')),
					$propsContainer
				)
			);

			// Render tree and properties
			renderHierarchyTree($treeContainer, moduleData);
			renderPropertyTable($propsContainer, moduleData);

		}).fail(function (code, result) {
			$root.removeClass('ss-hierarchy-loading').empty().append(
				$('<p>').addClass('error').text(
					mw.msg('structuresync-hierarchy-error') + ': ' + 
					(result.error && result.error.info ? result.error.info : code)
				)
			);
		});
	}

	/**
	 * Public API
	 */
	mw.StructureSyncHierarchy = {
		/**
		 * Render hierarchy visualization into a container.
		 * 
		 * @param {Element|jQuery|string} container Container element or selector
		 * @param {string} categoryTitle Category title to visualize
		 */
		renderInto: function (container, categoryTitle) {
			var $root = $(container);
			
			if ($root.length === 0) {
				mw.log.warn('StructureSyncHierarchy: Container not found');
				return;
			}

			if (!categoryTitle || typeof categoryTitle !== 'string') {
				$root.empty().append(
					$('<p>').addClass('error').text(
						mw.msg('structuresync-hierarchy-no-category')
					)
				);
				return;
			}

			fetchAndRender($root, categoryTitle);
		}
	};

	/**
	 * Auto-initialize: Check for containers with data-category attribute
	 * and automatically render the hierarchy.
	 */
	$(function () {
		$('.ss-hierarchy-block[data-category]').each(function () {
			var $container = $(this);
			var categoryTitle = $container.data('category');
			if (categoryTitle) {
				mw.StructureSyncHierarchy.renderInto($container, categoryTitle);
			}
		});
	});

}(mediaWiki, jQuery));

