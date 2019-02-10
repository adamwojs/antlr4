/*
 * Copyright (c) 2012-2017 The ANTLR Project. All rights reserved.
 * Use of this file is governed by the BSD 3-clause license that
 * can be found in the LICENSE.txt file in the project root.
 */

package org.antlr.v4.codegen.model;

import org.antlr.v4.codegen.OutputModelFactory;
import org.antlr.v4.codegen.model.decl.StructDecl;
import org.antlr.v4.runtime.misc.OrderedHashSet;

import java.util.ArrayList;
import java.util.List;

public class Parser extends Recognizer {
	public ParserFile file;

	@ModelElement public List<RuleFunction> funcs = new ArrayList<RuleFunction>();
	@ModelElement public OrderedHashSet<StructDecl> structDecls = new OrderedHashSet<StructDecl>();

	public Parser(OutputModelFactory factory, ParserFile file) {
		super(factory);
		this.file = file; // who contains us?
	}
}
