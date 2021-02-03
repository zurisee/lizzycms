// reveal.js


$('.lzy-reveal-controller-elem').each(function() {
	const $this = $( this );
	var $target = null;

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
});




$('body').on('click change', '.lzy-reveal-controller-elem', function(e) {
	e.stopPropagation();
	const $this = $( this );
	var type = false;
	var $target = null;

	if ($this.prop('tagName') === 'SELECT') {				// case dropdown:
		type = 'dropdown';
		$target = $( $( ':selected', $this ).attr('data-reveal-target') );

	} else {											// case radio and checkbox:
		type = this.type;
		$target = $( $this.attr('data-reveal-target') );
	}

	if ( type === 'dropdown') {							// case select:
		$('[data-reveal-target]', $this).each(function () {
			$( $(this).attr('data-reveal-target') ).parent().removeClass('lzy-elem-revealed');
			$(this).attr('aria-expanded', 'false');
		});

		// now open selected:
		$target.parent().addClass('lzy-elem-revealed');
		$this.attr('aria-expanded', 'true');
		return;

	} else if (type === 'radio') { 						// case radio: close all others
		$this.parent().siblings().each(function() {
			const $this1 = $('.lzy-reveal-controller-elem', $( this ));
			const $target1 = $( $this1.attr('data-reveal-target') );
			const $container1 = $target1.parent();
			$this1.attr('aria-expanded', 'false');
			$container1.removeClass('lzy-elem-revealed');
		});

	}
	if ( $this.prop('checked') ) { // open:
		// set margin-top according to elem height:
		const boundingBox = $target[0].getBoundingClientRect();
		const marginTop = (-10 - Math.round(boundingBox.height)) + 'px';
		$target.css({ marginTop: marginTop, transition: 'margin-top 0'});
		setTimeout(function () {
			$target.css({transition: 'margin-top 0.3s' });
			$this.attr('aria-expanded', 'true');
			$target.parent().addClass('lzy-elem-revealed');
		}, 20);

	} else { // close:
		$this.attr('aria-expanded', 'false');
		$target.parent().removeClass('lzy-elem-revealed');
	}
});
