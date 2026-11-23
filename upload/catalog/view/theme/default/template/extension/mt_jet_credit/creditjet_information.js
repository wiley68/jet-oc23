(function() {
	if (window.JetInformation) return;
	let active;

	function close(owner, restore = true) {
		if (!active || active.owner !== owner) return;
		const { overlay, focus } = active;
		active = null;
		overlay.remove();
		if (restore && focus && document.documentElement.contains(focus)) focus.focus();
	}

	window.JetInformation = {
		close,
		active: owner => Boolean(active && active.owner === owner),
		show(owner, message, focus) {
			if (active) close(active.owner, false);
			const overlay = document.createElement('div');
			overlay.className = 'jet-information-overlay';
			const box = document.createElement('div');
			box.className = 'jet-information-dialog';
			box.setAttribute('role', 'dialog');
			box.setAttribute('aria-modal', 'true');
			box.setAttribute('aria-labelledby', 'jet-information-title');
			box.setAttribute('aria-describedby', 'jet-information-message');
			const title = document.createElement('h2');
			title.id = 'jet-information-title';
			title.textContent = 'Информация';
			const text = document.createElement('p');
			text.id = 'jet-information-message';
			text.textContent = message;
			const button = document.createElement('button');
			button.type = 'button';
			button.textContent = 'Затвори';
			button.addEventListener('click', () => close(owner));
			box.appendChild(title);
			box.appendChild(text);
			box.appendChild(button);
			overlay.appendChild(box);
			active = { owner, overlay, button, focus };
			document.body.appendChild(overlay);
			button.focus();
		}
	};

	document.addEventListener('keydown', event => {
		if (!active) return;
		if (event.key === 'Escape' || event.key === 'Tab' || !active.overlay.contains(event.target)) {
			event.preventDefault();
			event.stopImmediatePropagation();
			if (event.key === 'Escape') close(active.owner);
			else active.button.focus();
		}
	}, true);
	document.addEventListener('focusin', event => {
		if (active && !active.overlay.contains(event.target)) active.button.focus();
	}, true);
	document.addEventListener('click', event => {
		if (active && !active.overlay.contains(event.target)) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}, true);
})();
