var j$ = jQuery.noConflict();

function DynamicTable(options) {
	// From options object
	this.attr = options.attr;
	this.value = options.value;
	this.idPrefix = options.idPrefix;
	this.templateRowSize = options.rowSize;	
	this.initialSettings = options.initialSettings == null ? {length: 0} : options.initialSettings;
	this.defaultSettings = options.defaultSettings;
	this.templateRowSegment = options.rowSegment;
	this.templateRowDataCount = options.rowDataCount;
	this.regularFindIndex = new RegExp (options.findIndex, 'g');
	this.regularFindValue = new RegExp (options.findValue, 'g');
	this.$anchor = j$('#' + options.anchorID);
	this.headerRow = options.headerRow;
	this.addButtonClass = options.addButtonClass;
	this.deleteButtonClass = options.deleteButtonClass;
	
	this.maxRows = 20;
	this.deleteInProgress = false;
	
	// Start the construction of a table
	this.$table = j$('<table id="group-table" id="' + this.idPrefix + 'table"></table>');
	this.table = this.$table[0];
	this.$anchor.append (this.$table);
	
	// Append the header row to the table
	this.$table.append ('<tr>' + this.headerRow + '<th><a id="' + this.idPrefix + 
		'add-row" class="' + this.addButtonClass + '" type="button">Add</a>' +
		'</th></tr>');
	//@todo take out class or button or type?
	//@todo make jquery object from string, append, and then use the object for
	// things like setting up event listeners.
	
	for (var i = 0; i < this.initialSettings.length; i ++ ) {
		var row = '<tr>';
		for (var k = 0; k < this.templateRowSegment.length; k ++) {
			row += this.templateRowSegment[k](this.initialSettings[i][k]);
			row = row.replace (this.regularFindIndex, i);
		}
		row += this.deleteButtonTableData(i);
		row += '</tr>';
		this.$table.append (row);			
	}	
	
	if (options.enabled === 0) {
		this.toggleSection();
	}
	
	this.initializeEventListeners();
}

/**
 * Generates event listeners for buttons
 */
DynamicTable.prototype.initializeEventListeners = function () {
	var scope = this;

	// Set up event listeners for each delete button.
	for (var i = 0; i <= this.amountOfRows(); i ++) {
		/**
		 * Create a function and call it for each iteration to store the value of the index at that iteration.
		 *	The function defined as the argument of .click will be able to access the variable at each index.
		 */
		(function (index) {
			scope.$getDelBtn(i).click(function () {
				scope.deleteRow(index);
			});
		})(i);
	}

	// Set up event listener for the delete all button.
	j$('#' + this.idPrefix + 'del-all').click(function () {
		scope.deleteAll();
	});
	
	// Set up event listener for the add button.
	j$('#' + this.idPrefix + 'add-row').click(function () {
		scope.addNewRow(scope);
	});
};

DynamicTable.prototype.deleteButtonTableData = function (index) {
	return '<td><a id="'+ this.idPrefix + 'del-row-' + index + '"class="' + this.deleteButtonClass + '"" value="Delete"' +
			'">Delete</a></td>';
};

/**
 * Adds a new row to the table of rows
 */
DynamicTable.prototype.addNewRow = function(scope) {	// If the current amount of rows (before one is added) is less the allowed amount of rows, we can add one more row
	
	if (this.templateRow === undefined) {
		for (var k = 0; k < this.templateRowSegment.length; k ++) {
			this.templateRow += this.templateRowSegment[k](this.defaultSettings[k]);
		}
	}

	var index = scope.amountOfRows();

	if (index < scope.maxRows) {

		/**
		 * Everything is zero-indexed, but the value of amountOfRows() is one more than the
		 * last index (since it represents the amount of rows).
		 * Because of this, we can use it here when we create the next table row, which must
		 * have an index of one more than the last index .
		 */
		
		var row = this.templateRow.replace (this.regularFindIndex, index);

		// Append a new row
		this.$table.append('<tr style="display: none;">'+
		row + this.deleteButtonTableData(index) + '</tr>' );

		// Give the delete button on this row a click event handler.
		this.$getDelBtn(index).click(function () {
			scope.deleteRow(index);
		});

		// The display was set to none, so fade in our new row.
		this.$getRow(index).animate({height: "toggle", opacity: "toggle"}, 300);
	}
	else {
		alert("Hey, you're making too many new rows! You can only make " + scope.maxRows + " rows!");
	}
};

/**
 * Reindexes the rows if needed
 *
 * Let's say there are 5 rows and you call deleteRow (2). Now there are 4 rows.
 * You call this function as reindexHTML(2) *AFTER* deleteRow (2) was called.
 *
 * Before deleteRow (2) was called, the indicies were originally: {0, 1, 2, 3, 4}.
 * After deleteRow (2) was called, the indicies were changed to: {0, 2, 3, 4} (which makes no sense)
 * We have to make the number sequential.
 *
 * This function will run replaceIndex 3 times:
 * 1st time: Replace 2 with 1: {0, 1, 3, 4}
 * 2nd time: Replace 3 with 2: {0, 1, 2, 4}
 * 3rd time: Replace 4 with 3; {0, 1, 2, 3}
 *
 * Now that makes more sense!
 *
 * Look at replaceIndex for this settings page's specific HTML relabelling.
 */
DynamicTable.prototype.reindexHTML = function(indexToDelete) {
	for (var i = indexToDelete; i <= this.amountOfRows(); i ++) {
		this.replaceIndex(i, i - 1);
	}
	this.deleteInProgress = false;
};

/**
 * Replaces the name array (which wordpress reads the value from) with the new index,
 * and replaces the delete event listener with the new delete index
 */
DynamicTable.prototype.replaceIndex = function(find, replace) {
	var scope = this;

	// Loop is run twice for the second dimension of the loop (which has a size of 2)
	for (var i = 0; i < 2; i ++) {
		j$('[' + this.attr + '="' + this.value + '[' + find + '][' + i + ']"]').attr(this.attr, this.value + '[' + replace + '][' + i +']');
	}

	// Unbind the current event handler for click and replace it with the new index to delete
	var $btn = this.$getDelBtn(find);
	$btn.attr('id', this.idPrefix + 'del-row-' + replace);
	$btn.unbind('click');

	// Closure needed here?
	(function(index) {
		$btn.click (function () {
			scope.deleteRow(index);
		});
	})(replace);

};
/**
 * Removes a row from the list
 */
DynamicTable.prototype.deleteRow = function(rowIndex, override) {
	if (this.deleteInProgress && override === undefined) return;

	this.deleteInProgress = true;
	var scope = this;
	var $row = this.$getRow(rowIndex);
	$row.animate({height: "toggle", opacity: "toggle"}, 300, function () {
		if (scope.amountOfRows() === 1) {

			/**
			 * If the user the deletes the last row. Not doing anything so far.
			 */
		}

		j$(this).remove();
		scope.reindexHTML(rowIndex + 1);
	});
};

/**
 * Deletes all the rows from the list
 */
DynamicTable.prototype.deleteAll = function() {
	if (confirm ("Are you sure you want to delete ALL rows? There is no undo for this action after the 'Save Changes' button is pressed.")) {
		for (var i = this.amountOfRows() - 1; i >= 0 ; i --) {
			this.deleteRow (i, true);
		}
	}
};

/**
 * Clears the input text box
 */
DynamicTable.prototype.clearRow = function(rowIndex) {
	this.$getRow(rowIndex).find('input[type=text], textarea').val("");
};

/**
 * Gets a row corresponding to an indexs
 */
DynamicTable.prototype.$getRow = function(rowIndex) {	// Alternatively j$('#row-table tr:nth-child(' + (rowToClear + 1 /*account for 0 starting value*/ ) + ')').
	return j$(this.table.rows[rowIndex + 1]); // +1 is to account for table header row
};

/**
 * Gets the delete button corresponding to an index
 */
DynamicTable.prototype.$getDelBtn = function(rowIndex) {
	return j$('#' + this.idPrefix + 'del-row-' + rowIndex);
};

/**
 * Gets the amount of rows
 */
DynamicTable.prototype.amountOfRows = function() {
	//if (this.table === undefined) return 0;
	return this.table.rows.length - 1; // -1 is to account for table header row
};


j$(document).ready(function () {
	
	var $tableSection = j$('.form-table').find('tr').eq(3);

	// If it's enabled, show it, otherwise, don't
	if ( enabled != 0) {
		$tableSection.show();
	}
	else {
		$tableSection.hide();
	}
	
	options.addButtonClass = "button";
	options.deleteButtonClass = "button";
	
	// Tell DynamicTable to replace this value with the current index of whatever row you're on
	options.findIndex = "%i%";
	
	options.headerRow = 
		'<th>Group Name</th><th>Role</th>';
		
	var roleExists = function (roles, roleThatMightExist) {
		for (role in roles) {
			if (roleThatMightExist === role) {
				return true;
			}
		}
		return false;
	};
	
	// Define how the settings defined in the options object are outputed for each table data
	options.rowSegment = [
		function (val) {
			return '<td><input type="text" '+ options.attr + 
				'="'+ options.value +'[' + options.findIndex +
				'][0]" size="'+ 40 + '" value="'+	val +'"></td>';
		},
		
		function (val) {
			var missingValueNotify = '';
			var missingValueText = '';
			if (!roleExists(roles, val)) {
				val = lowestPriorityRole[0];
				missingValueNotify = "style='background-color:red'";
				missingValueText = 'A mapped role has been deleted. Save changes with new role mapping to prevent lockout!';
				alert(missingValueText);
			}
			var r = "<td><select " + missingValueNotify +  " " + options.attr + "=" + options.value + "[" + options.findIndex + "][1]>";
			for (var prop in roles) {
				// console.log ("prop: " + prop + " role: " + val);
				if (prop == val) {
					r += "<option selected='selected' value='" + prop + "'>" + roles[prop] + "</option>";
				}
				else {
					r += "<option value='" + prop + "'>" + roles[prop] + "</option>";
				}
			}
			r += "<select></td>";
			
			return r;
		}
	];
	
	new DynamicTable (options);
	//var $tableSection = j$('#' + options.anchorID);

	// Toggle view of the group mapping section when the checkbox is clicked
	j$('#' + options.idPrefix + 'enable').click (function() {
		$tableSection.toggle(300);
	});
	
	var $sortable = j$('#sortable'); 
	$sortable.sortable({
		update: function (event, ui) {
			ui.item.find('input').val(ui.item.index()).attr('value', ui.item.index());
			$sortable.find('li').each(function (index, value) {
				j$(value).find('input').val (index).attr('value', index);
			});
		}
	});
});