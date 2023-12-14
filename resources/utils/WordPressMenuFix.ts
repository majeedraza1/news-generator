/**
 * As we are using hash based navigation, hack fix
 * to highlight the current selected menu
 */
class WordPressMenuFix {
	private menuRoot: HTMLElement;

	constructor(slug: string) {
		this.menuRoot = document.querySelector('#toplevel_page_' + slug);

		this.refreshHash();
		this.init();
	}

	init() {
		const mainLink = this.menuRoot.querySelector('a.wp-has-submenu');
		this.menuRoot.addEventListener('click', (event) => {
			const self = event.target as Element;

			// Remove all active link
			this.removeActive();

			if (mainLink.contains(self)) {
				this.activeFirstItem();
			} else {
				self.closest('li').classList.add('current');
			}
		});
	}

	activeFirstItem() {
		this.menuRoot
			.querySelector('ul.wp-submenu li.wp-first-item')
			.classList.add('current');
	}

	removeActive() {
		this.menuRoot
			.querySelectorAll('ul.wp-submenu li')
			.forEach((element) => element.classList.remove('current'));
	}

	refreshHash() {
		const currentUrl = window.location.href;
		this.menuRoot.querySelectorAll('ul.wp-submenu a').forEach((el) => {
			if (
				el.getAttribute('href') ===
				currentUrl.substring(currentUrl.indexOf('admin.php'))
			) {
				el.parentElement.classList.add('current');
			}
		});
	}
}

export default WordPressMenuFix;
