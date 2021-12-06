module.exports = {
  "*.{js,jsx,ts,tsx}": ["npm run format:provided"], //, "eslint"],
  "*.{ts,tsx}": [() => "tsc --noEmit"],
  "*.{scss,css}": ["npm run format:provided", "npm run lint:css"],
  "*.php": "bin/phpcs.sh",
  "composer.json": "composer validate --strict --no-check-all",
};
