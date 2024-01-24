class UtilityStylerParser {

	parse() {

		setTimeout( () => {

			const jsonOutput = this.extractAndParseUnocss();

			console.log('jsonOutput')
			console.log(jsonOutput)

			const apiUrl = '/wp-json/utility-styler/v1/save-css-json';
			const data = {
				css_json: jsonOutput,
				current_post: utilityStylerData.currentPostId,
			};

			fetch(apiUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json; charset=UTF-8'
				},
				body: JSON.stringify(data)
			})
			.then(response => response.text())
			.then(text => console.log('JSON saved:', text))
			.catch(error => console.error('Error saving JSON:', error));

		}, 2000)

	}

	extractAndParseUnocss() {

		const styleTag = document.querySelector('style[data-unocss-runtime-layer="default"]');

		if (!styleTag) {
				return '{}';
		}

		const cssContent = styleTag.textContent || '';
		return this.parseCssToJSON(cssContent);

	}

	parseCssToJSON(cssContent) {

    const cssRules = cssContent.split('}');
    const parsedRules = {};
    cssRules.forEach(rule => {
      if (rule.includes('{')) {
        const [selector, properties] = rule.split('{').map(item => item.trim());
        if (selector && properties && !selector.startsWith('/*')) {
          parsedRules[selector] = properties;
        }
      }
    });
    return JSON.stringify(parsedRules, null, 4);

	}

}

const utilityStylerParser = new UtilityStylerParser();
utilityStylerParser.parse();
