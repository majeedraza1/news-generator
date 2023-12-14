module.exports = {
	content: [
		'./resources/**/*.{vue,js,jsx,scss,ts,tsx}',
		'./templates/**/*.php',
	],
	important: true,
	variants: {
		extend: {},
	},
	plugins: [],
	corePlugins: {
		preflight: false,
	},
};
