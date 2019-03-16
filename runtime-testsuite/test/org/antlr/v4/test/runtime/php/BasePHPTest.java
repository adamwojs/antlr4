/*
 * Copyright (c) 2012-2017 The ANTLR Project. All rights reserved.
 * Use of this file is governed by the BSD 3-clause license that
 * can be found in the LICENSE.txt file in the project root.
 */

package org.antlr.v4.test.runtime.php;

import org.antlr.v4.test.runtime.ErrorQueue;
import org.antlr.v4.test.runtime.RuntimeTestSupport;
import org.antlr.v4.test.runtime.StreamVacuum;
import org.apache.commons.io.IOUtils;
import org.stringtemplate.v4.STGroup;
import org.stringtemplate.v4.STGroupFile;

import java.io.File;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.*;

import static org.antlr.v4.test.runtime.BaseRuntimeTest.antlrOnString;
import static org.antlr.v4.test.runtime.BaseRuntimeTest.writeFile;
import static org.junit.Assert.assertTrue;

public class BasePHPTest implements RuntimeTestSupport {

	/**
	 * Work directory for currently executed test
	 */
	private String testdir = null;

	/**
	 * Working directory for runtime tests
	 */
	private String basedir = null;

	/**
	 * If error during parser execution, store stderr here; can't return stdout
	 * and stderr. This doesn't trap errors from running antlr.
	 */
	protected String stderrDuringParse;

	/**
	 * Errors found while running antlr
	 */
	protected StringBuilder antlrToolErrors;

	/**
	 * Templates of the files which are generated during test execution.
	 */
	protected STGroup filesTemplates = new STGroupFile(
		BasePHPTest.class.getResource("PHPTestFiles.stg")
	);

	@Override
	public void testSetUp() throws Exception {
		// new output dir for each test
		String prop = System.getProperty("antlr-php-test-dir");
		if (prop != null && prop.length() > 0) {
			basedir = prop;
		} else {
			basedir = new File(System.getProperty("java.io.tmpdir"),  "/antlr").getAbsolutePath();
		}

		testdir = new File(basedir, getClass().getSimpleName() + "-" + Thread.currentThread().getName() + "-" + System.currentTimeMillis()).getAbsolutePath();

		Files.createDirectories(Paths.get(testdir));

		Path composerPath = Paths.get(basedir +  "/composer.json");
		if (Files.notExists(composerPath)) {
			boolean success = generateComposer();
			assertTrue(success);
			success = installDependencies();
			assertTrue(success);
		}

		antlrToolErrors = new StringBuilder();
	}

	@Override
	public void testTearDown() throws Exception {

	}

	@Override
	public void eraseTempDir() {
	}

	@Override
	public String getTmpDir() {
		return testdir;
	}

	@Override
	public String getStdout() {
		return null;
	}

	@Override
	public String getParseErrors() {
		return stderrDuringParse;
	}

	@Override
	public String getANTLRToolErrors() {
		if (antlrToolErrors.length() == 0) {
			return null;
		}
		return antlrToolErrors.toString();
	}

	@Override
	public String execLexer(
		String grammarFileName,
		String grammarStr,
		String lexerName,
		String input,
		boolean showDFA
	) {
		boolean success = generateAndBuildRecognizer(
			grammarFileName,
			grammarStr,
			null,
			lexerName, false, "-no-listener"
		);

		assertTrue(success);

		writeFile(testdir, "input", input);
		writeLexerTestFile(lexerName, showDFA);

		return execModule("Test.php");
	}

	@Override
	public String execParser(
		String grammarFileName,
		String grammarStr,
		String parserName,
		String lexerName,
		String listenerName,
		String visitorName,
		String startRuleName,
		String input,
		boolean showDiagnosticErrors
	) {
		boolean success = generateAndBuildRecognizer(
			grammarFileName,
			grammarStr,
			parserName,
			lexerName, false, "-visitor"
		);

		assertTrue(success);

		writeFile(testdir, "input", input);

		this.stderrDuringParse = null;

		if (parserName == null) {
			writeLexerTestFile(lexerName, false);
		} else {
			writeParserTestFile(
				parserName,
				lexerName,
				listenerName,
				visitorName,
				startRuleName,
				showDiagnosticErrors,
				false
			);
		}

		return execModule("Test.php");
	}

	protected boolean generateComposer() {
		final String runtimePath = getClass().getClassLoader().getResource("PHP").getPath();

		String output = filesTemplates
			.getInstanceOf("ComposerFile")
			.add("runtimePath", runtimePath)
			.render();

		writeFile(basedir, "composer.json", output);

		return true;
	}

	protected boolean installDependencies() {
		final String composerPath = "/usr/bin/composer";
		File tmpdirFile = new File(basedir);

		try {
			Process process = (new ProcessBuilder(composerPath, "install"))
				.directory(tmpdirFile)
				.start();
			process.waitFor();

			return true;
		} catch (Exception e) {
			e.printStackTrace();
			return false;
		}
	}

	protected boolean generateAndBuildRecognizer(
		String grammarFileName,
		String grammarStr,
		String parserName,
		String lexerName,
		boolean defaultListener,
		String... extraOptions
	) {
		ErrorQueue equeue =
			antlrOnString(testdir, "PHP", grammarFileName, grammarStr, defaultListener, extraOptions);
		if (!equeue.errors.isEmpty()) {
			return false;
		}

		List<String> files = new ArrayList<String>();
		if (lexerName != null) {
			files.add(lexerName + ".php");
		}
		if (parserName != null) {
			files.add(parserName + ".php");
			Set<String> optionsSet = new HashSet<String>(Arrays.asList(extraOptions));
			if (!optionsSet.contains("-no-listener")) {
				files.add(grammarFileName.substring(0, grammarFileName.lastIndexOf('.')) + "Listener.php");
			}
			if (optionsSet.contains("-visitor")) {
				files.add(grammarFileName.substring(0, grammarFileName.lastIndexOf('.')) + "Visitor.php");
			}
		}

		return true;
	}

	private String execModule(String fileName) {
		final String phpPath = "/usr/bin/php7.2";

		File tmpdirFile = new File(testdir);
		String modulePath = new File(tmpdirFile, fileName).getAbsolutePath();
		String inputPath = new File(tmpdirFile, "input").getAbsolutePath();

		try {
			ProcessBuilder builder = new ProcessBuilder(phpPath, modulePath, inputPath);
			builder.directory(tmpdirFile);

			Process process = builder.start();
			StreamVacuum stderrVacuum = new StreamVacuum(process.getErrorStream());
			stderrVacuum.start();
			process.waitFor();
			stderrVacuum.join();
			String output = IOUtils.toString(process.getInputStream());
			if (stderrVacuum.toString().length() > 0) {
				this.stderrDuringParse = stderrVacuum.toString();
			}

			return output;
		} catch (Exception e) {
			System.err.println("can't exec recognizer");
			e.printStackTrace(System.err);
		}

		return null;
	}

	protected void writeLexerTestFile(String lexerName, boolean showDFA) throws RuntimeException {
		String output = filesTemplates
			.getInstanceOf("LexerTestFile")
			.add("lexerName", lexerName)
			.add("showDFA", showDFA)
			.render();

		writeFile(testdir, "Test.php", output);
	}

	private void writeParserTestFile(
		String parserName,
		String lexerName,
		String listenerName,
		String visitorName,
		String startRuleName,
		boolean debug,
		boolean trace
	) {
		String output = filesTemplates
			.getInstanceOf("ParserTestFile")
			.add("parserName", parserName)
			.add("lexerName", lexerName)
			.add("listenerName", listenerName)
			.add("visitorName", visitorName)
			.add("parserStartRuleName", startRuleName)
			.add("debug", debug)
			.add("trace", trace)
			.render();

		writeFile(testdir, "Test.php", output);
	}
}
