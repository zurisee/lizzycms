// reveal.js

// init:
$('.lzy-reveal-controller-elem').each(function() {
	const $this = $( this );
	let $target = null;

	if ($this.prop('tagName') === 'SELECT') {		// case dropdown:
		$('[data-reveal-target]', $this).each(function () {
			$target = $( $(this).attr('data-reveal-target') );
			if (!$target.parent().hasClass('lzy-reveal-container')) {
				$target.wrap("<div class='lzy-reveal-container'></div>").show();
				$target.css('margin-top', '-10000px');
			}
			if ( this.selected ) {
				$target.parent().addClass('lzy-elem-revealed');
				$(this).attr('aria-expanded', 'true');
			} else {
				$(this).attr('aria-expanded', 'false');
			}
		});

	} else {											// case radio and checkbox:
		$target = $( $this.attr('data-reveal-target') );
		if (!$target.parent().hasClass('lzy-reveal-container')) {
			$target.wrap("<div class='lzy-reveal-container'></div>").show();
			$target.css('margin-top', '-10000px');
		}
		if (this.checked) {
			$this.attr('aria-expanded', 'true');
			$target.parent().addClass('lzy-elem-revealed');
		} else {
			$this.attr('aria-expanded', 'false');
		}
	}

	let $revealContainer = $target.closest('.lzy-reveal-container');
	$revealContainer.find(':focusable').each(function (){
		let $el = $(this);
		let tabindex = $el.attr('tabindex');
		if (typeof tabindex === 'undefined') {
			tabindex = 0;
		}
		$el.addClass('lzy-focus-disabled').attr('tabindex', -1).data('tabindex', tabindex);
	});
}); // init



// setup triggers:
$('body').on('change', '.lzy-reveal-controller-elem', function(e) {
	lzyOperateRevealPanel( this );
});
$('body').on('click', '.lzy-reveal-controller-elem', function(e) {
	e.stopImmediatePropagation();
	e.stopPropagation();
});
//Todo: make operatable by clicking on .lzy-reveal-controller:
// $('body').on('click', '.lzy-reveal-controller', function(e) {
// 	e.stopImmediatePropagation();
// 	e.stopPropagation();
// 	let $checkbox = $('.lzy-reveal-controller-elem', $( this ));
// 	mylog('click controller: ' + $checkbox.prop('checked'));
// 	$checkbox.prop('checked', !$checkbox.prop('checked'));
// 	mylog('click controller: ' + $checkbox.prop('checked'));
// 	// return false;
// });



function lzyOperateRevealPanel( that )
{
	const $revealController = $( that );
	let type = false;
	let $target = null;

	if ($revealController.prop('tagName') === 'SELECT') {				// case dropdown:
		type = 'dropdown';
		$target = $( $( ':selected', $revealController ).attr('data-reveal-target') );

	} else {											// case radio and checkbox:
		type = $revealController[0].type;
		$target = $( $revealController.attr('data-reveal-target') );
	}

	if ( type === 'dropdown') {							// case select:
		$('[data-reveal-target]', $revealController).each(function () {
			$( $(this).attr('data-reveal-target') ).parent().removeClass('lzy-elem-revealed');
			$(this).attr('aria-expanded', 'false');
		});

		// open selected:
		$target.parent().addClass('lzy-elem-revealed');
		$revealController.attr('aria-expanded', 'true');
		return;

	} else if (type === 'radio') { 						// case radio: close all others
		$revealController.parent().siblings().each(function() {
			const $revealController1 = $('.lzy-reveal-controller-elem', $( this ));
			const $target1 = $( $revealController1.attr('data-reveal-target') );
			const $container1 = $target1.parent();
			$revealController1.attr('aria-expanded', 'false');
			$container1.removeClass('lzy-elem-revealed');
		});

	}

	// now operate:
	const $revealContainer = $target.closest('.lzy-reveal-container');
	const boundingBox = $revealContainer[0].getBoundingClientRect();
	const marginTop = (-10 - Math.round(boundingBox.height)) + 'px';
	$target.css({ transition: 'margin-top 0', marginTop: marginTop });
	if ( !$revealContainer.hasClass('lzy-elem-revealed') ) { // open:
		// set margin-top according to elem height:
		$target.css({ transition: 'margin-top 0.3s' });
		setTimeout(function () {
			$revealController.attr('aria-expanded', 'true');
			$target.parent().addClass('lzy-elem-revealed');
		}, 20);

		// ensable all focusable elements inside reveal-container:
		$('.lzy-focus-disabled', $revealContainer).each(function () {
			let $el = $( this );
			let tabindex = $el.data('tabindex');
			$el.attr('tabindex', tabindex);
		});

	} else { // close:
		$revealController.attr('aria-expanded', 'false');
		$target.parent().removeClass('lzy-elem-revealed');

		// disable all focusable elements inside reveal-container:
		$('.lzy-focus-disabled', $revealContainer).each(function () {
			$( this ).attr('tabindex', -1);
		});
	}
} // lzyOperateRevealPanel