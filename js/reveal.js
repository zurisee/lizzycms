// reveal.js


$('.lzy-reveal-controller-elem').each(function() {
	const tagName = $( this ).prop("tagName");
	if ($( this ).prop("tagName") === 'SELECT') {

	} else {
		$(this).attr('aria-expanded', 'false');
		const $target = $( $(this).attr('data-reveal-target') );
		if (!$target.parent().hasClass('lzy-reveal-container')) {
			$target.css('margin-top', '-100vh');
			$target.wrap("<div class='lzy-reveal-container'></div>").show();
		}
	}
});

$('.lzy-reveal-controller-elem').change(function() {
	const $this = $( this );
	var type = false;
	var $target = false;
	if ($this.prop("tagName") === 'SELECT') {
		type = 'dropdown';
		$target = $( $( ':selected', $this ).attr('data-reveal-target') );
	} else {
		type = this.type;
		$target = $( $this.attr('data-reveal-target') );
	}
	const boundingBox = $target[0].getBoundingClientRect();
	$target.css('margin-top', (boundingBox.height * -1 - 10) + 'px');

	if ( type === 'dropdown') { // case select: close all others
		var $target1 = false;
		$('option', $this).each(function() {
			$target1 = $( $( this ).attr('data-reveal-target') );
			$target1.parent().removeClass('lzy-elem-revealed');
		});
		// now open selected:
		$target.parent().addClass('lzy-elem-revealed');
		return;
	}

	if (type === 'radio') { // case radio: close all others
		$this.parent().siblings().each(function() {
			const $this1 = $('.lzy-reveal-controller-elem', $( this ));
			const $target1 = $( $this1.attr('data-reveal-target') );
			const $container1 = $target1.parent();
			$this1.attr('aria-expanded', 'false');
			$container1.removeClass('lzy-elem-revealed');
		});

	}
	if ( $this.prop('checked') ) {
		$this.attr('aria-expanded', 'true');
		$target.parent().addClass('lzy-elem-revealed');
	} else {
		$this.attr('aria-expanded', 'false');
		$target.parent().removeClass('lzy-elem-revealed');
	}
});
