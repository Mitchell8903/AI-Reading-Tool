document.addEventListener("DOMContentLoaded", function () {
    const chatbotMessages = document.getElementById("chatbot-messages");
    const markdownBody = document.getElementById("markdown-body");
    const userInput = document.getElementById("user-input");
    const sendButton = document.getElementById("send-button");
    const prevButton = document.getElementById("prev-chapter");
    const nextButton = document.getElementById("next-chapter");
    const chapterTitle = document.getElementById("chapter-title");
    const chapterContent = document.getElementById("chapter-content");

    let conversationHistory = [];
    let currentChapter = 0;
    let chapters = [];

    // Configure marked to use marked-mathjax-extension for LaTeX support
    if (window.markedMathjax) {
        marked.use(window.markedMathjax());
    }

    // Function to count words in a string
    function countWords(str) {
        return str.trim().split(/\s+/).length;
    }

    // Function to analyze heading levels and their content
    function analyzeHeadings(content) {
        const lines = content.split('\n');
        const headings = [];
        let currentHeading = null;
        let currentContent = '';
        let currentLevel = 0;
        let buffer = [];
        let inHeading = false;
        
        for (const line of lines) {
            const match = line.match(/^(#{1,6})\s+(.+)$/);
            
            if (match) {
                // If we were in a heading, save it
                if (inHeading) {
                    headings.push({
                        level: currentLevel,
                        title: currentHeading,
                        content: currentContent.trim()
                    });
                }
                
                // Start new heading
                currentLevel = match[1].length;
                currentHeading = match[2];
                currentContent = line + '\n';
                buffer = [line];
                inHeading = true;
            } else {
                // Only accumulate content if we're in a heading
                if (inHeading) {
                    currentContent += line + '\n';
                    buffer.push(line);
                }
            }
        }
        
        // Add the last heading if we were in one
        if (inHeading) {
            headings.push({
                level: currentLevel,
                title: currentHeading,
                content: currentContent.trim()
            });
        }
        
        return headings;
    }

    // Function to group headings into chapters
    function groupHeadingsIntoChapters(headings) {
        if (headings.length === 0) return [];
        
        const chapters = [];
        const bestLevel = findBestHeadingLevel(headings);
        let currentChapter = null;
        let currentContent = '';
        
        // First pass: find the most common heading level
        const levelCounts = {};
        headings.forEach(heading => {
            levelCounts[heading.level] = (levelCounts[heading.level] || 0) + 1;
        });
        
        // Use the most common level if it exists, otherwise use the calculated best level
        const mostCommonLevel = Object.entries(levelCounts)
            .sort((a, b) => b[1] - a[1])[0]?.[0];
        const targetLevel = mostCommonLevel ? parseInt(mostCommonLevel) : bestLevel;
        
        for (const heading of headings) {
            if (heading.level === targetLevel) {
                // If we have a current chapter, save it
                if (currentChapter !== null) {
                    chapters.push({
                        title: currentChapter.title,
                        content: currentContent.trim()
                    });
                }
                // Start new chapter
                currentChapter = heading;
                currentContent = heading.content;
            } else if (heading.level > targetLevel) {
                // Add subheading content to current chapter
                currentContent += '\n' + heading.content;
            }
            // Skip headings of lower level (they should be handled by previous chapters)
        }
        
        // Add the last chapter
        if (currentChapter !== null) {
            chapters.push({
                title: currentChapter.title,
                content: currentContent.trim()
            });
        }
        
        return chapters;
    }

    // Function to find the best heading level for chapters
    function findBestHeadingLevel(headings) {
        const levelStats = {};
        const targetWords = window.targetWordCount || 300; // Default to 300 if not set
        
        // Calculate average chapter length for each heading level
        for (const heading of headings) {
            const level = heading.level;
            if (!levelStats[level]) {
                levelStats[level] = { total: 0, count: 0 };
            }
            const wordCount = countWords(heading.content);
            levelStats[level].total += wordCount;
            levelStats[level].count++;
        }
        
        // Find the heading level closest to target words per chapter
        let bestLevel = 1;
        let closestDiff = Infinity;
        
        for (const [level, stats] of Object.entries(levelStats)) {
            if (stats.count > 0) {
                const avgWords = stats.total / stats.count;
                const diff = Math.abs(avgWords - targetWords);
                if (diff < closestDiff) {
                    closestDiff = diff;
                    bestLevel = parseInt(level);
                }
            }
        }
        
        return bestLevel;
    }

    // Preprocess markdown to extract math regions and replace with placeholders
    function extractMath(markdown) {
        const mathBlocks = [];
        // Block math: $$...$$ (including newlines)
        markdown = markdown.replace(/\$\$([\s\S]+?)\$\$/g, (match, p1) => {
            mathBlocks.push({type: 'block', content: match});
            return `@@MATH_BLOCK_${mathBlocks.length - 1}@@`;
        });
        // Inline math: $...$
        markdown = markdown.replace(/\$([^$\n]+?)\$/g, (match, p1) => {
            mathBlocks.push({type: 'inline', content: match});
            return `@@MATH_INLINE_${mathBlocks.length - 1}@@`;
        });
        return {markdown, mathBlocks};
    }

    // Restore math regions after HTML parsing
    function restoreMath(html, mathBlocks) {
        html = html.replace(/@@MATH_BLOCK_(\d+)@@/g, (match, idx) => {
            return `<div class="math">${mathBlocks[idx].content}</div>`;
        });
        html = html.replace(/@@MATH_INLINE_(\d+)@@/g, (match, idx) => {
            return `<span class="math">${mathBlocks[idx].content}</span>`;
        });
        return html;
    }

    // Function to convert markdown to HTML (robust for LaTeX)
    function markdownToHtml(markdown) {
        const {markdown: md, mathBlocks} = extractMath(markdown);
        let html = marked.parse(md);
        html = restoreMath(html, mathBlocks);
        return html;
    }

    // Initialize chapters
    function initializeChapters() {
        const headings = analyzeHeadings(window.markdownContent);
        const bestLevel = findBestHeadingLevel(headings);
        chapters = groupHeadingsIntoChapters(headings);
        currentChapter = 0;
        chapterContent.innerHTML = '';
        if (chapters.length > 0) {
            const chapterDiv = document.createElement('div');
            chapterDiv.className = 'chapter';
            chapterDiv.id = `chapter-0`;
            chapterDiv.innerHTML = markdownToHtml(chapters[0].content);
            chapterContent.appendChild(chapterDiv);
        }
        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise();
        }
        updateNavigation();
    }

    // Update navigation buttons and title
    function updateNavigation() {
        prevButton.disabled = currentChapter === 0;
        nextButton.disabled = chapters.length === 0 || currentChapter === chapters.length - 1;
        chapterTitle.textContent = (chapters.length > 0 && chapters[currentChapter]) ? chapters[currentChapter].title : '';
    }

    // Add chapter navigation functionality
    prevButton.addEventListener('click', function() {
        if (currentChapter > 0) {
            switchChapter(currentChapter - 1);
        }
    });

    nextButton.addEventListener('click', function() {
        if (currentChapter < chapters.length - 1) {
            switchChapter(currentChapter + 1);
        }
    });

    function switchChapter(index) {
        chapterContent.innerHTML = '';
        if (chapters.length > index && chapters[index]) {
            const chapterDiv = document.createElement('div');
            chapterDiv.className = 'chapter';
            chapterDiv.id = `chapter-${index}`;
            chapterDiv.innerHTML = markdownToHtml(chapters[index].content);
            chapterContent.appendChild(chapterDiv);
        }
        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise();
        }
        currentChapter = index;
        updateNavigation();
    }

    // Initialize the page
    initializeChapters();

    function appendMessage(sender, text) {
        let messageDiv = document.createElement("div");
        messageDiv.classList.add(sender);
        messageDiv.textContent = text;
        chatbotMessages.appendChild(messageDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

        // Store the last 10 messages in conversation history
        conversationHistory.push({ sender, text });
        if (conversationHistory.length > 10) {
            conversationHistory.shift(); // Remove the oldest message if history exceeds 10 messages
        }
    }

    function getHighlightedText() {
        const highlightedElements = document.querySelectorAll(".highlighted");
        return Array.from(highlightedElements)
            .map((el) => el.tagName === "IMG" ? el.alt : el.textContent.trim())
            .join(" ");
    }

    function sendMessage(message, flag = "other") {
        if (!message.trim()) return; // Ignore empty messages
        appendMessage("user", message);

        // Clear dynamic options when sending any message
        document.getElementById("dynamic-options").innerHTML = "";

        const highlightedText = getHighlightedText();
        if (highlightedText) {
            message = `${highlightedText} - ${message}`;
        } else if (flag !== "other") {
            appendMessage("bot", "No text was selected.");
            return;
        }

        // Construct a message history string
        const messageHistory = conversationHistory.map(msg => `${msg.sender}: ${msg.text}`).join("\n");

        const payload = {
            prompt: message,
            response_type: flag,
            conversation_history: messageHistory // Send last 10 messages for context
        };

        const flaskUrl = `http://${window.flaskConfig.ip}:${window.flaskConfig.port}/assistant`;

        fetch(flaskUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        })
            .then(response => {
                if (!response.ok) throw new Error("Network response was not ok");
                return response.json();
            })
            .then(data => {
                appendMessage("bot", data.response);
            })
            .catch(error => appendMessage("bot", `Error: ${error.message}`));

        userInput.value = ""; // Clear input after sending
    }

    document.querySelectorAll(".chat-button").forEach(button => {
        button.addEventListener("click", function () {
            let buttonAction = this.getAttribute("data-message");
            let message = "";

            if (buttonAction === "clarify") {
                message = "Clarify the selected text.";
            }
            else if (buttonAction === "example") {
                message = "Provide an example for the selected text.";
            }
            else if (buttonAction === "test") {
                message = "Test me on the selected text.";
            }

            if (buttonAction) sendMessage(message, buttonAction);
        });
    });

    //Dynamic options
    document.getElementById("more-options").addEventListener("click", function () {
        const dynamicOptionsContainer = document.getElementById("dynamic-options");
        dynamicOptionsContainer.innerHTML = ""; // Clear previous options

        // Check for highlighted text
        const highlightedText = getHighlightedText();
        if (!highlightedText) {
            return;
        }

        const requestBody = JSON.stringify({
            selected_text: highlightedText
        });

        const flaskUrl = `http://${window.flaskConfig.ip}:${window.flaskConfig.port}/suggest-options`;

        // Call the Flask API directly
        fetch(flaskUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: requestBody
        })
            .then(response => {
                console.log("Raw response object:", response);
                return response.json();
            })
            .then(data => {
                console.log("Parsed JSON data:", data);
                if (!Array.isArray(data)) {
                    throw new Error("Invalid response format");
                }

                // Loop through the response options and create buttons
                data.forEach(optionText => {
                    const newButton = document.createElement("button");
                    newButton.classList.add("chat-button", "dynamic-button");
                    newButton.textContent = optionText.toString();

                    // Add event listener to send selected option to the chatbot
                    newButton.addEventListener("click", function () {
                        sendMessage(optionText, "other");
                    });

                    // Append the button to the dynamic options container
                    dynamicOptionsContainer.appendChild(newButton);
                });
            })
            .catch(error => {
                console.error("Error fetching options:", error);
                const errorButton = document.createElement("button");
                errorButton.classList.add("chat-button", "dynamic-button");
                errorButton.textContent = "Failed to load options";
                dynamicOptionsContainer.appendChild(errorButton);
            });
    });

    const dynamicButtons = document.querySelectorAll(".dynamic-button");
    dynamicButtons.forEach((button) => {
        button.addEventListener("click", () => {
            dynamicOptionsContainer.innerHTML = ""; // Clear previous options
        });
    });

    // Markdown Element Highlighting
    markdownBody.addEventListener("click", (event) => {
        const highlightableTags = ["P", "LI", "IMG", "H1", "H2", "H3", "H4", "H5", "H6", "pre"];
        const target = event.target;

        // Don't handle clicks on chapter buttons
        if (target.classList.contains('chapter-button')) {
            return;
        }

        if (highlightableTags.includes(target.tagName)) {
            target.classList.toggle("highlighted");
        } else {
            document.querySelectorAll(".highlighted").forEach(el => el.classList.remove("highlighted"));
        }
        
        // Clear dynamic options when highlighting changes
        document.getElementById("dynamic-options").innerHTML = "";
    });

    // Send message when the send button is clicked
    sendButton.addEventListener("click", function () {
        sendMessage(userInput.value);
    });

    // Allow pressing "Enter" to send messages
    userInput.addEventListener("keypress", function (event) {
        if (event.key === "Enter") sendMessage(userInput.value);
    });
});
