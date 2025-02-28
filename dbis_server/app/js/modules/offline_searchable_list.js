/**
 * A general script for implementing search functionality.
 * 
 * This script features improved search features, e.g. tokenization and 
 * similarity matching.
 * 
 * Typical structure of a searchable list:
 * 
 * <input id="searchfield"/>
 * <ul>
 *  <li class="entry">
 *   <!-- entry needs a span with classtag "content"!-->
 *   <span class="search-content">
 *    Some term to be searched
 *   </span>
 *  </li>
 * </ul>
 */

class OfflineSearchableList 
{
    constructor(searchField, entries)
    {
        const that = this;
        this.entries = entries;
        this.searchField = searchField;
        this.filter(searchField.value);
        this.searchField.addEventListener("keyup", function (event) {
            that.filter(event.target.value);
        });
        
        
    }
    
    focus()
    {
        this.searchField.focus();
        this.searchField.selectionStart = this.searchField.value.length;
        this.searchField.selectionEnd = this.searchField.value.length;
    }

    tokenize(strInput)
    {
        return strInput.toLowerCase().split(" ");
    }

    /**
     * 
     * @param {string} needle
     * @param {string} haystack
     * @param {float} threshold Higher threshold will match more selectively
     * @returns {Number}
     */
    matchStrings(needle, haystack, threshold = 0.95)
    {
        const searchTokens = this.tokenize(needle);
        const lowercaseHaystack = haystack.toLowerCase();
        const matchingTokens = searchTokens.filter((token) => {
            return lowercaseHaystack.includes(token);
        });
        return matchingTokens.length / searchTokens.length >= threshold;
    }

    filter(term = "") {
        const that = this;
        const entries = this.entries.filter(e => {
            return !e.classList.contains("search-ignore");
        })
        entries.forEach(entry => {
            const entryTerm = entry.querySelector(".search-content").innerHTML;
            if (!term || term === "")
            {
                entry.classList.remove("is-hidden");
                return;
            }
            
            that.matchStrings(term, entryTerm) ?
                    entry.classList.remove("is-hidden") :
                    entry.classList.add("is-hidden");
        });
    }
}

export {
    OfflineSearchableList
};