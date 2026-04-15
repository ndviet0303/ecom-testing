package com.example;

import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;

class LoopBranchProgramStatementCoverageTest {

    private final LoopBranchProgram program = new LoopBranchProgram();

    @Test
    void shouldReturnOneWhenScoreIsAtLeastFive() {
        int result = program.classifyAndScore(new int[]{1, 2, 3});
        assertEquals(1, result);
    }

    @Test
    void shouldReturnZeroAndCoverZeroAndNegativeBranches() {
        int result = program.classifyAndScore(new int[]{0, -1});
        assertEquals(0, result);
    }
}
