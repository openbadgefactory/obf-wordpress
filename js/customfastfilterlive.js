/**
 * fastLiveFilter jQuery plugin 1.0.3
 * 
 * Copyright (c) 2011, Anthony Bush
 * License: <http://www.opensource.org/licenses/bsd-license.php>
 * Project Website: http://anthonybush.com/projects/jquery_fast_live_filter/
 **/

(function($) { 
    $(document).ready(function($) {
        //jQuery.fn.customFastLiveFilter = function(list, options) {
        jQuery.fn.extend({customFastLiveFilter: function(list, options) {
            // Options: input, list, timeout, callback
            options = options || {};
            list = jQuery(list);
            var input = this;
            var lastFilter = '';
            var timeout = options.timeout || 0;
            var callback = options.callback || function() {};
            var groupFilterElements = jQuery(options.groupFilterSelect) || {};
            var forceShowSelect = options.forceShowSelect || '';
            var numToDisplay = options.resultDisplayCount || 10;
            var resultDisplaySelect = jQuery(options.resultDisplaySelect) || null;

            var keyTimeout;
            
            if ($.isEmptyObject(input) || input.length < 1) {
                return;
            }
            
                        
            var showCountReached = function(numShown) {
                if (numToDisplay == 0) {
                    return false;  
                } else if (numToDisplay - 1 >= numShown) {
                    return false;
                }
                return true;
            };
            
            var filterFunction = function() {
                    // var startTime = new Date().getTime();
                    var filter = input.val().toLowerCase();
                    var li, innerText;
                    var numShown = 0, numTotal = 0;
                    var groupMatch = true;
                    var activeGroupFilter = [];
                    groupFilterElements.filter('.active').each(function() { activeGroupFilter.push(jQuery(this).attr('value')); });

                    for (var i = 0; i < len; i++) {
                            li = lis[i];
                            innerText = !options.selector ? 
                                    (li.textContent || li.innerText || "") : 
                                    $(li).find(options.selector).text();
                            var itemGroups = JSON.parse(!options.selector ? 
                                    $(li).attr('data-groups') : 
                                    $(li).find(options.selector).attr('data-groups')), itemGroup = itemGroups.length > 0 ? itemGroups[0] : '';
                            var inSelectedGroups = (activeGroupFilter.indexOf(itemGroup) > -1 || activeGroupFilter.indexOf('all') > -1);
                            var forceShow = false;
                            if (forceShowSelect.length > 0 && $(li).find(forceShowSelect).length > 0) {
                                forceShow = true;
                            }
                            if (!showCountReached(numShown) && forceShow || (!showCountReached(numShown) && inSelectedGroups && innerText.toLowerCase().indexOf(filter) >= 0)) {
                                    if (li.style.display == "none") {
                                            li.style.display = oldDisplay;
                                    }
                                    numShown++;
                            } else {
                                    if (li.style.display != "none") {
                                            li.style.display = "none";
                                    }
                            }
                            numTotal++;
                    }
                    callback(numShown, numTotal);
                    // var endTime = new Date().getTime();
                    // console.log('Search for ' + filter + ' took: ' + (endTime - startTime) + ' (' + numShown + ' results)');
                    return false;
            };

            // NOTE: because we cache lis & len here, users would need to re-init the plugin
            // if they modify the list in the DOM later.  This doesn't give us that much speed
            // boost, so perhaps it's not worth putting it here.
            var lis = list.children();
            var len = lis.length;
            var oldDisplay = len > 0 ? lis[0].style.display : "block";
            callback(len, len); // do a one-time callback on initialization to make sure everything's in sync
            groupFilterElements.click(function(e,ele) {
                var el = $(e.target);
                var val = el.attr('value');
                
                var activeGroupFilter = [];
                groupFilterElements.filter('.active').each(function() { activeGroupFilter.push(jQuery(this).attr('value')); });
                
                if (val == 'all') {
                    el.siblings().removeClass('active');
                    el.addClass('active');
                } else {
                    el.toggleClass('active');
                    if (el.hasClass('active')) {
                        groupFilterElements.filter('[value="all"]').removeClass('active');
                    } else if (groupFilterElements.filter('.active').length == 0) {
                        groupFilterElements.filter('[value="all"]').addClass('active');
                    }
                }
                
                filterFunction();
                return false;
            });

            input.change(filterFunction).keydown(function() {
                    clearTimeout(keyTimeout);
                    keyTimeout = setTimeout(function() {
                            if( input.val() === lastFilter ) return;
                            lastFilter = input.val();
                            input.change();
                    }, timeout);
            });
            
            if (!$.isEmptyObject(resultDisplaySelect) && resultDisplaySelect.length == 1) {
                numToDisplay = resultDisplaySelect.val(); 
                resultDisplaySelect.change(function() {
                   numToDisplay = resultDisplaySelect.val(); 
                   filterFunction();
                });
            };
            filterFunction();
            return this; // maintain jQuery chainability
        }});
    
        
        var filterExtraInfoHidden = $('.filter-extra-info span.hidden-count');
        var filterExtraInfoShown = $('.filter-extra-info span.shown-count');
        var countCallback = function(shown, total) {
            filterExtraInfoHidden.text(total - shown);
            filterExtraInfoShown.text(shown);
        };

        $('.filter-input').customFastLiveFilter('.filterable-items-list', 
            {
                groupFilterSelect: '.filter-options .filter-option',
                forceShowSelect: ':checked',
                callback: countCallback,
                resultDisplaySelect: '.filter-item-count'
            }
        );
    })
})( jQuery);
