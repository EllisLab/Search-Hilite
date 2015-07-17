# Search-Hilite

Using a CSS class, this plugin will highlight the terms searched for whenever someone arrives at a template using various search engines.

The following five searches engines are currently supported: A9, Dogpile, ExpressionEngine, Google, Lycos, and Yahoo

## Installation

Add this style to your CSS and modify the color as you see fit.
```
.hilite { background-color: #ff0; }
```

Wrap your fields and content with the plugin tags.

```
{exp:search_hilite}
	<p>{summary}</p>

	<p>{body}</p>
{/exp:search_hilite}
```

Optionally create a welcome message for incoming search engine users:

```
{exp:search_hilite welcome='true'}
	<p>Welcome {engine} user!</p>
	We noticed that you have arrived here via {engine} and have
	highlighted your search terms: {search_words}.
{/exp:search_hilite}
```

This message will only display if a Search Engine referrer is available and search terms are found in the referrer URL.

## Usage

### `{exp:search_hilite}`

#### Example Usage

```
{exp:search_hilite}
	<p>{summary}</p>

	<p>{body}</p>
{/exp:search_hilite}
```

#### Parameters

- `partial` - (yes)
- `welcome` - (true)

#### Variables

- `{engine}` - 
- `{search_words}` - 

## Change Log

### 2.0

- Updated plugin to be 3.0 compatible.

### 1.3.1

- Fixed a PHP error.

### 1.3

- Updated plugin to be 2.0 compatible.

### 1.2.2

- Fixed a bug to allow for the search term to be highlighted multiple times within xhtml under most circumstances.

### 1.2.1

- Made plugin compatible with PHP 4.4 and above

### 1.2

- Fixed a bug when there is a quote used in the original search engine's search.

### 1.1

- Added the {search_words} varible.
- Added ExpressionEngine's search support
- Caching of search words by search engine possible. Disabled by default. To enable, go into the plugin file and set the class variable `$write_cache` to `'y'`. There will now be a file created for each search engine in a newly create `/system/cache/search_hilite/` directory. Inside each file will be the search words and the time when the page was loaded for those terms. The file for a specific search engine will not be created until a search comes in from that search engine.
