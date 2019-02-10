package org.antlr.v4.codegen.target;

import org.antlr.v4.Tool;
import org.antlr.v4.codegen.CodeGenerator;
import org.antlr.v4.codegen.Target;
import org.antlr.v4.codegen.UnicodeEscapes;
import org.antlr.v4.tool.ast.GrammarAST;
import org.stringtemplate.v4.STGroup;
import org.stringtemplate.v4.StringRenderer;

import java.util.Arrays;
import java.util.HashSet;
import java.util.Locale;
import java.util.Set;

public class PHPTarget extends Target {

	/**
	 * The Java target can cache the code generation templates.
	 */
	private static final ThreadLocal<STGroup> targetTemplates = new ThreadLocal<STGroup>();

	protected static final String[] phpKeywords = {
		"__halt_compiler", "abstract", "and", "array", "as", "break", "callable", "case", "catch", "class", "clone",
		"const", "continue", "declare", "default", "die", "do", "echo", "else", "elseif", "empty", "enddeclare",
		"endfor", "endforeach", "endif", "endswitch", "endwhile", "eval", "exit", "extends", "final", "for", "foreach",
		"function", "global", "goto", "if", "implements", "include", "include_once", "instanceof", "insteadof",
		"interface", "isset", "list", "namespace", "new", "or", "print", "private", "protected", "public", "require",
		"require_once", "return", "static", "switch", "throw", "trait", "try", "unset", "use", "var", "while", "xor"
	};

	/** Avoid grammar symbols in this set to prevent conflicts in gen'd code. */
	protected final Set<String> badWords = new HashSet<String>();

	public PHPTarget(CodeGenerator gen) {
		super(gen, "PHP");
	}

	public Set<String> getBadWords() {
		if (badWords.isEmpty()) {
			addBadWords();
		}

		return badWords;
	}

	protected void addBadWords() {
		badWords.addAll(Arrays.asList(phpKeywords));
		badWords.add("rule");
		badWords.add("parserRule");
	}

	@Override
	public String getVersion() {
		return Tool.VERSION; // Java and tool versions move in lock step
	}

	@Override
	protected void appendUnicodeEscapedCodePoint(int codePoint, StringBuilder sb) {
		UnicodeEscapes.appendJavaStyleEscapedCodePoint(codePoint, sb);
	}

	@Override
	protected boolean visibleGrammarSymbolCausesIssueInGeneratedCode(GrammarAST idNode) {
		return getBadWords().contains(idNode.getText());
	}

	@Override
	public boolean supportsOverloadedMethods() {
		return false;
	}

	@Override
	protected STGroup loadTemplates() {
		STGroup result = targetTemplates.get();
		if (result == null) {
			result = super.loadTemplates();
			result.registerRenderer(String.class, new PHPTarget.PHPStringRenderer(), true);
			targetTemplates.set(result);
		}

		return result;
	}

	protected static class PHPStringRenderer extends StringRenderer {

		@Override
		public String toString(Object o, String formatString, Locale locale) {
			// TODO: Rename "java-escape" to "php-escape"
			if ("java-escape".equals(formatString)) {
				// 5C is the hex code for the \ itself
				return ((String)o).replace("\\u", "\\u005Cu");
			}

			return super.toString(o, formatString, locale);
		}
	}

	@Override
	public String encodeIntAsCharEscape(int v) {
		return "0x" + Integer.toHexString(v) + ", ";
	}
}
