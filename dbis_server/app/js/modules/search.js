class Search 
{
    static matchStrings(needle, haystack, threshold=0.95)
    {        
        const searchTokens = this.tokenize(needle);
        const lowercaseHaystack = haystack.toLowerCase();
        const matchingTokens = searchTokens.filter((token) => {
            return lowercaseHaystack.includes(token);
        });
        return matchingTokens.length / searchTokens.length >= threshold;        
    }
    
    static tokenize(inStr)
    {        
        return inStr.toLowerCase().split(" ");
    }
}

export {Search};