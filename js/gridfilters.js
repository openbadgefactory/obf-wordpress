jQuery(document).ready(function($) {
    var GRIDFILTERS = (function( $ ) {
        //'use strict';

        var $grid = $('#obf-badges'),
            $filterOptions = $('.shuffle-options.filter-options'),
            $sizer = $grid.find('.shuffle__sizer'),
        //init
        // @see http://vestride.github.io/Shuffle/#demo
        init = function() {

            // None of these need to be executed synchronously
            setTimeout(function() {
              listen();
              setupFilters();
            }, 100);

            // You can subscribe to custom events.
            // shrink, shrunk, filter, filtered, sorted, load, done
            $grid.on('loading.shuffle done.shuffle shrink.shuffle shrunk.shuffle filter.shuffle filtered.shuffle sorted.shuffle layout.shuffle', function(evt, shuffle) {
              // Make sure the browser has a console
              if ( window.console && window.console.log && typeof window.console.log === 'function' ) {
                console.log( 'Shuffle:', evt.type );
              }
            });
            // instantiate the plugin
            $grid.shuffle({
              itemSelector: '.obf-badge',
              sizer: $sizer
            });
            return $grid;
        },
        setupFilters = function() { // Set up button clicks
            var $btns = $filterOptions.children();
            $btns.on('click', function() {
                  var $this = $(this),
                      isActive = $this.hasClass( 'active' ),
                      group = isActive ? 'all' : $this.data('group');

                  // Hide current label, show current label in title
                  if ( !isActive ) {
                    $('.filter-options .active').removeClass('active');
                  }

                  $this.toggleClass('active');
                  var shuffle = {};
                  shuffle.group = group;

                  // Filter elements
                  $grid.shuffle('shuffle', group );
                  return false;
            });

            $btns = null;
        },
        listen = function() {

        };

        return {
            init: init,
            setupFilters: setupFilters
        };
    }( jQuery ));

    GRIDFILTERS.init();
});
