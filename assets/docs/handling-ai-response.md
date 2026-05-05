# Handling AI API Responses for Problem Generation

The trick is to tell the AI to respond in JSON so you always get a predictable structure you can parse and render. Instead of getting a wall of text back, you get clean fields you can plug straight into your UI.

## Your prompt should look something like this:

```js
const prompt = `Generate a math problem for a 8 year old kid.
Respond ONLY in valid JSON, no extra text, no markdown, like this:
{
  "question": "What is 5 + 3?",
  "type": "multiple_choice",  
  "options": ["6", "8", "9", "7"],
  "correct_answer": "8",
  "hint": "Count on your fingers starting from 5",
  "explanation": "5 + 3 means adding 3 to 5, which gives us 8"
}`;
```

## Then parse and use the response:

```js
async function getProblem(grade, subject) {
  const raw = await callAPI(prompt); // your existing API call
  
  // strip markdown fences just in case the AI adds them
  const cleaned = raw.replace(/```json|```/g, "").trim();
  const problem = JSON.parse(cleaned);

  return problem;
  // problem.question       → show this to the kid
  // problem.options        → render as buttons
  // problem.correct_answer → check against kid's answer
  // problem.hint           → show if they're stuck
  // problem.explanation    → show after they answer
}
```

## Then checking the answer is just:

```js
function checkAnswer(problem, kidAnswer) {
  return kidAnswer === problem.correct_answer;
}
```

## Key things to define in your JSON schema:

- `question` — what the kid sees
- `type` — `multiple_choice`, `true_false`, or `open_ended`
- `options` — array of choices (for multiple choice)
- `correct_answer` — for checking
- `hint` — for when they're stuck
- `explanation` — shown after answering, reinforces learning
